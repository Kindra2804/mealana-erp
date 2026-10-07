<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../lager/LagerService.php';
require_once __DIR__ . '/../lager/LagerRepository.php';
require_once __DIR__ . '/../kunden/KundenService.php';
require_once __DIR__ . '/../dokumente/DokumentRepository.php';
require_once __DIR__ . '/../dokumente/DokumentService.php';
require_once __DIR__ . '/../auftraege/AuftragRepository.php';
require_once __DIR__ . '/../auftraege/Positionsrechnung.php';
require_once __DIR__ . '/../auftraege/AuftragAbschluss.php';

/**
 * HaendlerService – Händler-Außenlager / Konsignation (Jacky 2026-10-01).
 *
 * Ware liegt beim Händler, bleibt aber unser Bestand (eigenes Lager, lager_beziehung =
 * 'haendler_aussenlager', lager.kunde_id = Händler-Kunde).
 *
 * - Lieferung:   Umbuchung eigenes Lager → Händler-Lager, Lieferschein (HL-…) mit
 *                Händlerpreis netto + empfohlenem Endkunden-VK. Der Preis gilt ab Lieferung:
 *                jede Lieferzeile merkt ihn sich (preis_netto), menge_offen = noch beim Händler.
 * - Verkauf:     Händler meldet verkaufte Mengen (oder Restbestand → Differenz). Abgebaut
 *                wird FIFO aus den Lieferzeilen, daraus entsteht ein Auftrag (kanal 'haendler',
 *                Netto-Preisbasis) + Rechnung; erst jetzt wird der Bestand im Händler-Lager
 *                abgebucht. Preise lassen sich in der Meldung vor dem Abrechnen korrigieren.
 * - Rücknahme:   Umbuchung zurück ins eigene Lager (HR-…), FIFO-Abbau.
 * - Schwund:     verloren/beschädigt beim Händler, wird NICHT verrechnet (HS-…), FIFO-Abbau.
 *
 * Händlerpreis: eigener Artikelpreis der Händler-Kundengruppe (artikel_preise.netto_vk),
 * sonst Endkunden-Netto-VK abzüglich kundengruppen.rabatt_prozent.
 */
class HaendlerService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Händler / Lager ──────────────────────────────────────────────────────

    public function getHaendlerGruppe(): array|false
    {
        return $this->db->query("SELECT * FROM kundengruppen WHERE typ = 'haendler' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }

    public function setStandardRabatt(?float $prozent): array
    {
        $g = $this->getHaendlerGruppe();
        if (!$g) return ['erfolg' => false, 'fehler' => ['Keine Händler-Kundengruppe vorhanden.']];
        if ($prozent !== null && ($prozent < 0 || $prozent >= 100)) return ['erfolg' => false, 'fehler' => ['Rabatt zwischen 0 und 99 %.']];
        $this->db->prepare("UPDATE kundengruppen SET rabatt_prozent = ? WHERE id = ?")->execute([$prozent, $g['id']]);
        Logger::log('haendler.rabatt', 'kundengruppen', (int)$g['id'], ['rabatt_prozent' => $prozent]);
        return ['erfolg' => true];
    }

    public function getLager(int $kundeId): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM lager WHERE kunde_id = ? AND lager_beziehung = 'haendler_aussenlager'");
        $stmt->execute([$kundeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Alle Händler mit Außenlager + Warenwert (zu Händlerpreisen) */
    public function getAlle(): array
    {
        $rows = $this->db->query("
            SELECT l.id AS lager_id, l.kunde_id, l.name, l.aktiv,
                   (SELECT COALESCE(SUM(lb.bestand), 0) FROM lagerbestand lb WHERE lb.lager_id = l.id) AS stueck,
                   (SELECT COALESCE(SUM(p.menge_offen * p.preis_netto), 0) FROM haendler_beleg_positionen p
                    JOIN haendler_belege b ON b.id = p.beleg_id AND b.typ = 'lieferung' WHERE b.kunde_id = l.kunde_id) AS wert_netto,
                   (SELECT MAX(b.erstellt_am) FROM haendler_belege b WHERE b.kunde_id = l.kunde_id AND b.typ = 'verkauf') AS letzte_abrechnung
            FROM lager l
            WHERE l.lager_beziehung = 'haendler_aussenlager'
            ORDER BY l.name
        ")->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /** Kunde als Händler einrichten: Kundengruppe Händler + Außenlager */
    public function lagerAnlegen(int $kundeId): array
    {
        $kunde = (new KundenService())->getById($kundeId);
        if (!$kunde) return ['erfolg' => false, 'fehler' => ['Kunde nicht gefunden.']];
        if ($this->getLager($kundeId)) return ['erfolg' => true];
        $gruppe = $this->getHaendlerGruppe();
        if (!$gruppe) return ['erfolg' => false, 'fehler' => ['Keine Händler-Kundengruppe vorhanden.']];

        $name = $this->kundenName($kunde);
        $this->db->prepare("
            INSERT INTO lager (name, typ, aktiv, fuer_offline_kasse_waehlbar, lager_beziehung, kunde_id)
            VALUES (?, 'extern', 1, 0, 'haendler_aussenlager', ?)
        ")->execute([mb_substr('Händler: ' . $name, 0, 50), $kundeId]);
        $lagerId = (int)$this->db->lastInsertId();
        if ((int)$kunde['kundengruppe_id'] !== (int)$gruppe['id']) {
            $this->db->prepare("UPDATE kunden SET kundengruppe_id = ? WHERE id = ?")->execute([$gruppe['id'], $kundeId]);
        }
        Logger::log('haendler.lager_anlegen', 'lager', $lagerId, ['kunde_id' => $kundeId]);
        return ['erfolg' => true, 'lager_id' => $lagerId];
    }

    /** Adresse des Händlers als Snapshot (gewünschter Typ, sonst Standard-/Hauptadresse) */
    public function adresse(int $kundeId, string $typ): ?array
    {
        $adressen = (new KundenService())->getAdressen($kundeId);
        if (!$adressen) return null;
        $ad = null;
        foreach ($adressen as $a) { if ($a['adresstyp'] === $typ) { $ad = $a; break; } }
        $ad ??= $adressen[0];
        return [
            'firmenname' => $ad['firma'] ?? '', 'vorname' => $ad['vorname'] ?? '', 'nachname' => $ad['nachname'] ?? '',
            'strasse' => trim(($ad['strasse'] ?? '') . ' ' . ($ad['hausnummer'] ?? '')),
            'plz' => $ad['plz'] ?? '', 'ort' => $ad['ort'] ?? '', 'land' => $ad['land'] ?? 'AT',
        ];
    }

    public function kundenName(array $kunde): string
    {
        $name = trim(($kunde['vorname'] ?? '') . ' ' . ($kunde['nachname'] ?? ''));
        if (!empty($kunde['ist_firma']) && !empty($kunde['firmenname'])) $name = $kunde['firmenname'];
        return $name ?: ('Kd. ' . ($kunde['kundennummer'] ?? ''));
    }

    // ── Preise ────────────────────────────────────────────────────────────────

    /** @return array{preis_netto: ?float, vk_brutto: ?float, steuer_prozent: float, quelle: string} */
    public function preis(int $artikelId): array
    {
        $g = $this->getHaendlerGruppe();
        $stmt = $this->db->prepare("
            SELECT s.satz,
                   (SELECT ap.netto_vk FROM artikel_preise ap WHERE ap.artikel_id = a.id AND ap.kundengruppen_id = ? LIMIT 1) AS h_netto,
                   (SELECT ap.brutto_vk FROM artikel_preise ap JOIN kundengruppen kg ON kg.id = ap.kundengruppen_id AND kg.ist_standard = 1
                    WHERE ap.artikel_id = a.id LIMIT 1) AS vk_brutto
            FROM artikel a LEFT JOIN steuerklassen s ON s.id = a.steuerklasse_id WHERE a.id = ?
        ");
        $stmt->execute([(int)($g['id'] ?? 0), $artikelId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) return ['preis_netto' => null, 'vk_brutto' => null, 'steuer_prozent' => 20.0, 'quelle' => 'fehlt'];
        $satz = (float)($r['satz'] ?? 20);
        $vk   = $r['vk_brutto'] !== null ? (float)$r['vk_brutto'] : null;
        if ($r['h_netto'] !== null && (float)$r['h_netto'] > 0) {
            return ['preis_netto' => round((float)$r['h_netto'], 2), 'vk_brutto' => $vk, 'steuer_prozent' => $satz, 'quelle' => 'artikel'];
        }
        if ($vk !== null && $g && $g['rabatt_prozent'] !== null) {
            $netto = $vk / (1 + $satz / 100) * (1 - (float)$g['rabatt_prozent'] / 100);
            return ['preis_netto' => round($netto, 2), 'vk_brutto' => $vk, 'steuer_prozent' => $satz, 'quelle' => 'rabatt'];
        }
        return ['preis_netto' => null, 'vk_brutto' => $vk, 'steuer_prozent' => $satz, 'quelle' => 'fehlt'];
    }

    // ── Bestand beim Händler ──────────────────────────────────────────────────

    /** Artikel beim Händler mit Menge, Händlerpreis(en) aus den offenen Lieferzeilen */
    public function getBestand(int $kundeId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.artikel_id, a.artikelnummer, a.name, SUM(p.menge_offen) AS menge,
                   MIN(p.preis_netto) AS preis_min, MAX(p.preis_netto) AS preis_max,
                   SUM(p.menge_offen * p.preis_netto) AS wert_netto,
                   (SELECT p2.preis_netto FROM haendler_beleg_positionen p2 JOIN haendler_belege b2 ON b2.id = p2.beleg_id
                    WHERE b2.kunde_id = b.kunde_id AND b2.typ = 'lieferung' AND p2.artikel_id = p.artikel_id AND p2.menge_offen > 0
                    ORDER BY b2.erstellt_am, p2.id LIMIT 1) AS preis_fifo,
                   MAX(p.vk_brutto) AS vk_brutto, MAX(p.steuer_prozent) AS steuer_prozent,
                   (SELECT code FROM artikel_codes WHERE artikel_id = p.artikel_id AND typ = 'GTIN13' LIMIT 1) AS ean
            FROM haendler_beleg_positionen p
            JOIN haendler_belege b ON b.id = p.beleg_id AND b.typ = 'lieferung'
            JOIN artikel a ON a.id = p.artikel_id
            WHERE b.kunde_id = ? AND p.menge_offen > 0
            GROUP BY p.artikel_id, a.artikelnummer, a.name, b.kunde_id
            ORDER BY a.artikelnummer
        ");
        $stmt->execute([$kundeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Baut eine Menge FIFO aus den offenen Lieferzeilen ab.
     * @return array<int, array{position_id:int, menge:float, preis_netto:float, steuer_prozent:float, charge:?string}>
     */
    private function fifoAbbau(int $kundeId, int $artikelId, float $menge): array
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.menge_offen, p.preis_netto, p.steuer_prozent, p.charge
            FROM haendler_beleg_positionen p JOIN haendler_belege b ON b.id = p.beleg_id
            WHERE b.kunde_id = ? AND b.typ = 'lieferung' AND p.artikel_id = ? AND p.menge_offen > 0
            ORDER BY b.erstellt_am, p.id
            FOR UPDATE
        ");
        $stmt->execute([$kundeId, $artikelId]);
        $teile = [];
        $rest  = $menge;
        $upd   = $this->db->prepare("UPDATE haendler_beleg_positionen SET menge_offen = menge_offen - ? WHERE id = ?");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $z) {
            if ($rest <= 0.0001) break;
            $nimm = min($rest, (float)$z['menge_offen']);
            $upd->execute([$nimm, $z['id']]);
            $teile[] = ['position_id' => (int)$z['id'], 'menge' => $nimm, 'preis_netto' => (float)$z['preis_netto'],
                        'steuer_prozent' => (float)$z['steuer_prozent'], 'charge' => $z['charge']];
            $rest -= $nimm;
        }
        if ($rest > 0.0001) throw new RuntimeException('Beim Händler liegen nicht genug Stück dieses Artikels.');
        return $teile;
    }

    // ── Buchungen ────────────────────────────────────────────────────────────

    /**
     * Lieferung, Rücknahme oder Schwund buchen.
     * $positionen: [['artikel_id'=>…, 'menge'=>…, 'charge'=>?], …]
     */
    public function buchen(int $kundeId, string $typ, array $positionen, ?string $notiz, int $benutzerId, int $eigenesLagerId = 1): array
    {
        if (!in_array($typ, ['lieferung', 'ruecknahme', 'schwund'], true)) return ['erfolg' => false, 'fehler' => ['Ungültige Buchungsart.']];
        $lager = $this->getLager($kundeId);
        if (!$lager) return ['erfolg' => false, 'fehler' => ['Händler hat kein Außenlager.']];
        if ($typ === 'schwund' && !trim((string)$notiz)) return ['erfolg' => false, 'fehler' => ['Bei Schwund bitte den Grund angeben (verloren, beschädigt …).']];

        $sauber = [];
        $fehler = [];
        foreach ($positionen as $p) {
            $menge = round((float)str_replace(',', '.', (string)($p['menge'] ?? 0)), 3);
            if ($menge <= 0) continue;
            $a = $this->db->prepare("SELECT id, artikelnummer, name, partner_id, vaterartikel_id,
                                            (SELECT COUNT(*) FROM artikel k WHERE k.vaterartikel_id = artikel.id) AS kinder
                                     FROM artikel WHERE id = ?");
            $a->execute([(int)($p['artikel_id'] ?? 0)]);
            $art = $a->fetch(PDO::FETCH_ASSOC);
            if (!$art) { $fehler[] = 'Unbekannter Artikel.'; continue; }
            if ($art['partner_id']) { $fehler[] = $art['name'] . ' ist Partnerware und kann nicht an Händler gehen.'; continue; }
            if ((int)$art['kinder'] > 0) { $fehler[] = $art['name'] . ': bitte die konkrete Variante wählen.'; continue; }
            $preis = $this->preis((int)$art['id']);
            if ($typ === 'lieferung' && $preis['preis_netto'] === null) {
                $fehler[] = $art['artikelnummer'] . ' ' . $art['name'] . ': kein Händlerpreis (Standard-Rabatt festlegen oder Händlerpreis am Artikel pflegen).';
                continue;
            }
            $sauber[] = $art + ['menge' => $menge, 'charge' => trim((string)($p['charge'] ?? '')) ?: null, 'preis' => $preis];
        }
        if ($fehler) return ['erfolg' => false, 'fehler' => $fehler];
        if (!$sauber) return ['erfolg' => false, 'fehler' => ['Keine Mengen eingetragen.']];

        $lagerSvc = new LagerService();
        $this->db->beginTransaction();
        try {
            $nummer = (new DokumentRepository())->naechsteNummer('haendler_' . $typ, (int)date('Y'));
            $this->db->prepare("INSERT INTO haendler_belege (kunde_id, lager_id, typ, nummer, von_lager_id, notiz, benutzer_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$kundeId, $lager['id'], $typ, $nummer, $typ === 'schwund' ? null : $eigenesLagerId, $notiz ?: null, $benutzerId ?: null]);
            $belegId = (int)$this->db->lastInsertId();
            $posStmt = $this->db->prepare("
                INSERT INTO haendler_beleg_positionen
                    (beleg_id, artikel_id, artikelnummer, bezeichnung, menge, menge_offen, charge, preis_netto, vk_brutto, steuer_prozent, lieferung_position_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($sauber as $p) {
                if ($typ === 'lieferung') {
                    $r = $lagerSvc->umbucheZwischenLager((int)$p['id'], $eigenesLagerId, (int)$lager['id'], $p['menge'], $benutzerId, $p['charge'], 'Lieferung an Händler ' . $nummer);
                    if (empty($r['erfolg'])) throw new RuntimeException($p['name'] . ': ' . ($r['fehler'] ?? 'Umbuchung fehlgeschlagen'));
                    $posStmt->execute([$belegId, $p['id'], $p['artikelnummer'], $p['name'], $p['menge'], $p['menge'], $p['charge'],
                                       $p['preis']['preis_netto'], $p['preis']['vk_brutto'], $p['preis']['steuer_prozent'], null]);
                    continue;
                }
                // Rücknahme / Schwund: FIFO aus den Lieferzeilen, Lagerbewegung je Teilmenge
                foreach ($this->fifoAbbau($kundeId, (int)$p['id'], $p['menge']) as $t) {
                    $charge = $p['charge'] ?? $t['charge'];
                    if ($typ === 'ruecknahme') {
                        $r = $lagerSvc->umbucheZwischenLager((int)$p['id'], (int)$lager['id'], $eigenesLagerId, $t['menge'], $benutzerId, $charge, 'Rücknahme vom Händler ' . $nummer);
                    } else {
                        $r = $lagerSvc->warenSchwund(['artikel_id' => $p['id'], 'lager_id' => $lager['id'], 'menge' => $t['menge'], 'charge' => $charge,
                                                      'referenz' => 'Schwund beim Händler ' . $nummer, 'notiz' => $notiz, 'benutzer_id' => $benutzerId]);
                    }
                    if (empty($r['erfolg'])) throw new RuntimeException($p['name'] . ': ' . (is_array($r['fehler'] ?? null) ? implode(' ', $r['fehler']) : ($r['fehler'] ?? 'Buchung fehlgeschlagen')));
                    $posStmt->execute([$belegId, $p['id'], $p['artikelnummer'], $p['name'], $t['menge'], 0, $charge,
                                       $t['preis_netto'], null, $t['steuer_prozent'], $t['position_id']]);
                }
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => [$e->getMessage()]];
        }
        Logger::log('haendler.' . $typ, 'haendler_belege', $belegId, ['kunde_id' => $kundeId, 'nummer' => $nummer]);
        return ['erfolg' => true, 'id' => $belegId, 'nummer' => $nummer];
    }

    /**
     * Verkaufsmeldung abrechnen: FIFO-Abbau → Auftrag (kanal 'haendler', netto) + Rechnung,
     * Bestand im Händler-Lager abbuchen. $positionen: [['artikel_id', 'menge', 'preis_netto'?], …]
     * -- ein übergebener preis_netto ersetzt den Lieferpreis (Preiskorrektur vor der Rechnung).
     */
    public function verkaufAbrechnen(int $kundeId, array $positionen, ?string $notiz, int $benutzerId): array
    {
        $lager = $this->getLager($kundeId);
        if (!$lager) return ['erfolg' => false, 'fehler' => ['Händler hat kein Außenlager.']];
        $kunde = (new KundenService())->getById($kundeId);
        if (!$kunde) return ['erfolg' => false, 'fehler' => ['Kunde nicht gefunden.']];

        $meldung = [];
        foreach ($positionen as $p) {
            $menge = round((float)str_replace(',', '.', (string)($p['menge'] ?? 0)), 3);
            if ($menge <= 0) continue;
            $override = isset($p['preis_netto']) && $p['preis_netto'] !== '' && $p['preis_netto'] !== null
                ? round((float)str_replace(',', '.', (string)$p['preis_netto']), 4) : null;
            if ($override !== null && $override < 0) return ['erfolg' => false, 'fehler' => ['Preis darf nicht negativ sein.']];
            $meldung[] = ['artikel_id' => (int)$p['artikel_id'], 'menge' => $menge, 'override' => $override];
        }
        if (!$meldung) return ['erfolg' => false, 'fehler' => ['Keine verkauften Mengen eingetragen.']];

        $lagerSvc = new LagerService();
        $repo     = new AuftragRepository();
        $this->db->beginTransaction();
        try {
            // Positionen: je Artikel + Preis eine Zeile (FIFO kann mehrere Preise liefern)
            $zeilen = [];
            $abbau  = [];
            foreach ($meldung as $m) {
                $art = $this->db->prepare("SELECT id, artikelnummer, name FROM artikel WHERE id = ?");
                $art->execute([$m['artikel_id']]);
                $a = $art->fetch(PDO::FETCH_ASSOC);
                foreach ($this->fifoAbbau($kundeId, $m['artikel_id'], $m['menge']) as $t) {
                    $preis = $m['override'] ?? $t['preis_netto'];
                    $key   = $m['artikel_id'] . '|' . number_format($preis, 4, '.', '') . '|' . $t['steuer_prozent'];
                    $zeilen[$key] ??= ['artikel_id' => $m['artikel_id'], 'bezeichnung' => $a['name'], 'artikelnummer' => $a['artikelnummer'],
                                       'menge' => 0, 'einzelpreis_netto' => $preis, 'steuer_prozent' => $t['steuer_prozent']];
                    $zeilen[$key]['menge'] += $t['menge'];
                    $abbau[] = $t + ['artikel_id' => $m['artikel_id'], 'artikelnummer' => $a['artikelnummer'], 'bezeichnung' => $a['name'], 'preis' => $preis];
                }
            }

            $netto = 0.0; $steuer = 0.0;
            foreach ($zeilen as &$z) {
                $r = Positionsrechnung::zeile($z['einzelpreis_netto'], $z['menge'], 0, $z['steuer_prozent'], Positionsrechnung::NETTO);
                $z['gesamtpreis_netto'] = $r['netto'];
                $netto += $r['netto']; $steuer += $r['steuer'];
            }
            unset($z);

            $adresse = $this->adresse($kundeId, 'rechnung');
            $snapshot = [
                'name' => $this->kundenName($kunde), 'vorname' => $kunde['vorname'] ?? '', 'nachname' => $kunde['nachname'] ?? '',
                'firma' => $kunde['firmenname'] ?? '', 'email' => $kunde['email'] ?? '', 'uid_nummer' => $kunde['uid_nummer'] ?? '',
            ];
            $auftragId = $repo->insert([
                'kunden_id' => $kundeId, 'kunden_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'lieferadresse_snapshot' => null,
                'rechnungsadresse_snapshot' => $adresse ? json_encode($adresse, JSON_UNESCAPED_UNICODE) : null,
                'kanal' => 'haendler', 'kanal_auftrag_id' => null, 'shop_id' => null,
                'zahlungsstatus' => 'ausstehend', 'lieferstatus' => 'versendet',
                'zahlungsart' => 'rechnung', 'lieferart' => 'abholung', 'versandklasse_id' => null,
                'zahlungsbedingung_id' => null, 'gutschein_id' => null, 'gutschein_betrag' => 0, 'versandkosten' => 0,
                'rabatt_gesamt' => 0, 'nettobetrag' => round($netto, 2), 'steuerbetrag' => round($steuer, 2),
                'bruttobetrag' => round($netto + $steuer, 2),
                'notiz_intern' => 'Verkaufsmeldung Händler' . ($notiz ? ': ' . $notiz : ''),
                'notiz_versand' => null, 'kontakt_notiz' => null, 'erstellt_von' => $benutzerId,
            ]);
            $i = 0;
            foreach ($zeilen as $z) {
                $posId = $repo->insertPosition([
                    'auftrag_id' => $auftragId, 'artikel_id' => $z['artikel_id'], 'charge' => null,
                    'bezeichnung' => $z['bezeichnung'], 'ean' => null, 'menge' => $z['menge'], 'menge_geliefert' => $z['menge'],
                    'einzelpreis_netto' => $z['einzelpreis_netto'], 'steuer_prozent' => $z['steuer_prozent'], 'rabatt_prozent' => 0,
                    'gesamtpreis_netto' => $z['gesamtpreis_netto'], 'konfig_freitext' => null, 'sort_order' => $i++,
                ]);
                $this->db->prepare("UPDATE auftrag_positionen SET preisbasis = 'netto' WHERE id = ?")->execute([$posId]);
            }
            $auftragNr = $this->db->query("SELECT auftrag_nr FROM auftraege WHERE id = " . (int)$auftragId)->fetchColumn();
            $repo->logStatus($auftragId, ['lieferstatus' => [null, 'versendet'], 'zahlungsstatus' => [null, 'ausstehend']], 'Verkaufsmeldung Händler abgerechnet', $benutzerId);

            // Beleg der Meldung + Bestand im Händler-Lager abbuchen
            $this->db->prepare("INSERT INTO haendler_belege (kunde_id, lager_id, typ, nummer, auftrag_id, notiz, benutzer_id) VALUES (?, ?, 'verkauf', ?, ?, ?, ?)")
                ->execute([$kundeId, $lager['id'], 'VM-' . $auftragNr, $auftragId, $notiz ?: null, $benutzerId ?: null]);
            $belegId = (int)$this->db->lastInsertId();
            $posStmt = $this->db->prepare("
                INSERT INTO haendler_beleg_positionen (beleg_id, artikel_id, artikelnummer, bezeichnung, menge, menge_offen, charge, preis_netto, steuer_prozent, lieferung_position_id)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
            ");
            foreach ($abbau as $t) {
                $posStmt->execute([$belegId, $t['artikel_id'], $t['artikelnummer'], $t['bezeichnung'], $t['menge'], $t['charge'], $t['preis'], $t['steuer_prozent'], $t['position_id']]);
                $r = $lagerSvc->warenausgang(['artikel_id' => $t['artikel_id'], 'lager_id' => $lager['id'], 'menge' => $t['menge'], 'charge' => $t['charge'],
                                              'referenz' => 'Verkauf Händler ' . $auftragNr, 'benutzer_id' => $benutzerId]);
                if (empty($r['erfolg'])) throw new RuntimeException($t['bezeichnung'] . ': ' . ($r['fehler'] ?? 'Abbuchung fehlgeschlagen'));
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => [$e->getMessage()]];
        }

        // Rechnung nach dem Commit (PDF-Erzeugung außerhalb der Transaktion)
        // Vollrechnung (Ware ist beim Händler schon verkauft); abgeschlossen erst mit Zahlung
        $re = (new DokumentService())->erstelleVollrechnung($auftragId, $benutzerId);
        AuftragAbschluss::pruefe($auftragId, $benutzerId);
        Logger::log('haendler.verkauf', 'auftraege', $auftragId, ['kunde_id' => $kundeId, 'rechnung' => $re['erfolg'] ?? false]);
        return ['erfolg' => true, 'auftrag_id' => $auftragId, 'auftrag_nr' => $auftragNr,
                'rechnung' => !empty($re['erfolg']), 'rechnung_fehler' => $re['fehler'] ?? null];
    }

    // ── Belege / Nachverfolgung ──────────────────────────────────────────────

    public function getBelege(int $kundeId): array
    {
        $stmt = $this->db->prepare("
            SELECT b.*, u.formularname AS benutzer_name,
                   (SELECT SUM(menge) FROM haendler_beleg_positionen WHERE beleg_id = b.id) AS stueck,
                   (SELECT SUM(menge * preis_netto) FROM haendler_beleg_positionen WHERE beleg_id = b.id) AS wert_netto,
                   r.rechnung_nr, a.zahlungsstatus
            FROM haendler_belege b
            LEFT JOIN benutzer u ON u.id = b.benutzer_id
            LEFT JOIN rechnungen r ON r.auftrag_id = b.auftrag_id AND r.storniert = 0
            LEFT JOIN auftraege a ON a.id = b.auftrag_id
            WHERE b.kunde_id = ? ORDER BY b.erstellt_am DESC, b.id DESC
        ");
        $stmt->execute([$kundeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBeleg(int $belegId): array|false
    {
        $stmt = $this->db->prepare("SELECT b.*, u.formularname AS benutzer_name FROM haendler_belege b LEFT JOIN benutzer u ON u.id = b.benutzer_id WHERE b.id = ?");
        $stmt->execute([$belegId]);
        $beleg = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$beleg) return false;
        $pos = $this->db->prepare("SELECT * FROM haendler_beleg_positionen WHERE beleg_id = ? ORDER BY artikelnummer, id");
        $pos->execute([$belegId]);
        $beleg['positionen'] = $pos->fetchAll(PDO::FETCH_ASSOC);
        return $beleg;
    }

    public function getBewegungen(int $kundeId, int $limit = 300): array
    {
        $stmt = $this->db->prepare("
            SELECT lb.erstellt_am, lb.bewegungstyp, lb.menge, lb.bestand_nachher, lb.charge, lb.referenz, lb.notiz,
                   a.artikelnummer, a.name, u.formularname AS benutzer_name
            FROM lager_bewegungen lb
            JOIN lager l ON l.id = lb.lager_id AND l.kunde_id = ? AND l.lager_beziehung = 'haendler_aussenlager'
            JOIN artikel a ON a.id = lb.artikel_id
            LEFT JOIN benutzer u ON u.id = lb.benutzer_id
            ORDER BY lb.erstellt_am DESC, lb.id DESC LIMIT " . (int)$limit
        );
        $stmt->execute([$kundeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Daten für Lieferschein/Rücknahme/Schwund-PDF */
    public function belegDokument(int $belegId): ?array
    {
        $beleg = $this->getBeleg($belegId);
        if (!$beleg || $beleg['typ'] === 'verkauf') return null;
        $kunde = (new KundenService())->getById((int)$beleg['kunde_id']);
        $adresse = $this->adresse((int)$beleg['kunde_id'], 'lieferung');
        $firma = (new DokumentRepository())->ladeFirmaDaten();
        $logo  = $this->db->query("SELECT logo_pfad FROM shops WHERE id = 1")->fetchColumn() ?: 'img/logos/logo_mealana.png';
        $pfad  = __DIR__ . '/../../../public/' . $logo;
        $summeNetto = 0.0; $summeVk = 0.0;
        foreach ($beleg['positionen'] as $p) { $summeNetto += $p['menge'] * $p['preis_netto']; $summeVk += $p['menge'] * (float)$p['vk_brutto']; }
        return [
            'beleg' => $beleg, 'kunde' => $kunde, 'kunde_name' => $this->kundenName($kunde ?: []), 'adresse' => $adresse,
            'firma' => $firma, 'logo_base64' => file_exists($pfad) ? base64_encode(file_get_contents($pfad)) : '',
            'summe_netto' => round($summeNetto, 2), 'summe_vk' => round($summeVk, 2),
            'stueck' => array_sum(array_column($beleg['positionen'], 'menge')),
        ];
    }
}
