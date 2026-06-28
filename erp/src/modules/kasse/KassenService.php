<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../lager/LagerService.php';

class KassenService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Kasse ────────────────────────────────────────────────────────────────

    public function getKasse(int $kasseId = 1): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM kassen WHERE id = :id AND aktiv = 1");
        $stmt->execute([':id' => $kasseId]);
        return $stmt->fetch() ?: null;
    }

    public function getAlleKassen(): array
    {
        return $this->db->query("SELECT * FROM kassen WHERE aktiv = 1 ORDER BY id")->fetchAll();
    }

    // ── Artikel-Lookup für Scan ───────────────────────────────────────────────

    /**
     * Sucht einen Artikel per EAN (exakt) oder Artikelnummer (exakt/LIKE).
     * Gibt Preisdaten (Standard-Kundengruppe) und Lagerbestand im angegebenen Lager zurück.
     */
    public function findArtikelByCode(string $code, int $lagerId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.id,
                a.name                AS bezeichnung,
                a.artikelnummer,
                a.vaterartikel_id,
                a.ist_vater,
                a.charge_pflicht,
                a.ueberverkauf_erlaubt,
                a.aktiv,
                sk.satz               AS steuer_prozent,
                COALESCE(
                    (SELECT ap.brutto_vk
                     FROM artikel_preise ap
                     INNER JOIN kundengruppen kg ON kg.id = ap.kundengruppen_id AND kg.ist_standard = 1
                     WHERE ap.artikel_id = a.id
                       AND (ap.gueltig_ab IS NULL OR ap.gueltig_ab <= CURDATE())
                       AND (ap.gueltig_bis IS NULL OR ap.gueltig_bis >= CURDATE())
                     ORDER BY ap.gueltig_ab DESC LIMIT 1
                    ), 0
                )                     AS brutto_vk,
                COALESCE(
                    (SELECT SUM(lb.bestand)
                     FROM lagerbestand lb
                     WHERE lb.artikel_id = a.id AND lb.lager_id = :lager_id
                    ), 0
                )                     AS bestand_physisch,
                COALESCE(
                    (SELECT SUM(r.menge)
                     FROM reservierungen r
                     WHERE r.artikel_id = a.id AND r.lager_id = :lager_id2
                       AND r.status = 'offen'
                    ), 0
                )                     AS bestand_reserviert,
                (SELECT ac2.code
                 FROM artikel_codes ac2
                 WHERE ac2.artikel_id = a.id AND ac2.typ = 'GTIN13'
                 LIMIT 1
                )                     AS ean,
                (SELECT ab.dateiname
                 FROM artikel_bilder ab
                 WHERE ab.artikel_id = a.id
                 ORDER BY ab.position ASC LIMIT 1
                )                     AS bild_dateiname
            FROM artikel a
            LEFT JOIN steuerklassen sk ON sk.id = a.steuerklasse_id
            WHERE a.aktiv = 1
              AND (
                  EXISTS (SELECT 1 FROM artikel_codes ac WHERE ac.artikel_id = a.id AND ac.code = :code AND ac.typ = 'GTIN13')
                  OR a.artikelnummer = :code2
              )
            LIMIT 1
        ");
        $stmt->execute([':code' => $code, ':code2' => $code, ':lager_id' => $lagerId, ':lager_id2' => $lagerId]);
        $artikel = $stmt->fetch();
        if (!$artikel) return null;

        $artikel['bestand_verkaufbar'] = max(0, (float)$artikel['bestand_physisch'] - (float)$artikel['bestand_reserviert']);

        // Bei Vater-Artikel: Kinder zurückgeben statt Artikel selbst
        if ($artikel['ist_vater']) {
            $artikel['kinder'] = $this->getKinderFuerKasse((int)$artikel['id'], $lagerId);
            $artikel['typ']    = 'vater';
        } else {
            $artikel['typ']    = 'artikel';
            if ($artikel['charge_pflicht']) {
                $artikel['fifo_charge'] = $this->getFifoCharge((int)$artikel['id'], $lagerId);
            }
        }

        return $artikel;
    }

    private function getKinderFuerKasse(int $vaterId, int $lagerId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.id,
                a.name                AS bezeichnung,
                a.artikelnummer,
                a.charge_pflicht,
                sk.satz               AS steuer_prozent,
                COALESCE(
                    (SELECT ap.brutto_vk
                     FROM artikel_preise ap
                     INNER JOIN kundengruppen kg ON kg.id = ap.kundengruppen_id AND kg.ist_standard = 1
                     WHERE ap.artikel_id = a.id
                       AND (ap.gueltig_ab IS NULL OR ap.gueltig_ab <= CURDATE())
                       AND (ap.gueltig_bis IS NULL OR ap.gueltig_bis >= CURDATE())
                     ORDER BY ap.gueltig_ab DESC LIMIT 1
                    ), 0
                )                     AS brutto_vk,
                COALESCE(
                    (SELECT SUM(lb.bestand) FROM lagerbestand lb
                     WHERE lb.artikel_id = a.id AND lb.lager_id = :lid), 0
                )                     AS lagerbestand,
                (SELECT ac.code FROM artikel_codes ac
                 WHERE ac.artikel_id = a.id AND ac.typ = 'GTIN13' LIMIT 1) AS ean
            FROM artikel a
            LEFT JOIN steuerklassen sk ON sk.id = a.steuerklasse_id
            WHERE a.vaterartikel_id = :vater_id AND a.aktiv = 1
            ORDER BY a.name
        ");
        $stmt->execute([':vater_id' => $vaterId, ':lid' => $lagerId]);
        return $stmt->fetchAll();
    }

    /** FIFO: Älteste Charge mit Bestand > 0 im Lager */
    public function getFifoCharge(int $artikelId, int $lagerId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT charge FROM lagerbestand
            WHERE artikel_id = :aid AND lager_id = :lid
              AND charge IS NOT NULL AND bestand > 0
            ORDER BY erstellt_am ASC
            LIMIT 1
        ");
        $stmt->execute([':aid' => $artikelId, ':lid' => $lagerId]);
        $row = $stmt->fetch();
        return $row ? $row['charge'] : null;
    }

    // ── Bon erstellen ─────────────────────────────────────────────────────────

    /**
     * Erstellt einen Kassenbon inkl. Positionen und Lagerbuchungen.
     *
     * $bonDaten: kasse_id, lager_id, zahlungsart, bruttobetrag,
     *            gegeben?, rueckgeld?, bar_betrag?, karten_betrag?,
     *            gutschein_code?, gutschein_betrag?, auftrag_id?, kunden_id?, notiz?
     * $positionen: [{artikel_id?, bezeichnung, ean?, menge, einzelpreis_brutto,
     *               steuer_prozent, rabatt_prozent, charge?}]
     */
    public function erstelleBon(array $bonDaten, array $positionen, int $benutzerId): array
    {
        if (empty($positionen)) {
            return ['erfolg' => false, 'fehler' => 'Keine Positionen übergeben.'];
        }

        $kasseId = (int)($bonDaten['kasse_id'] ?? 1);
        $lagerId = (int)($bonDaten['lager_id'] ?? 1);

        $this->db->beginTransaction();
        try {
            $bonNr = $this->naechsteBonNr($kasseId);

            $stmt = $this->db->prepare("
                INSERT INTO kassen_bons
                    (bon_nr, typ, kasse_id, auftrag_id, kunden_id, zahlungsart,
                     bruttobetrag, gegeben, rueckgeld, bar_betrag, karten_betrag,
                     gutschein_code, gutschein_betrag, benutzer_id, notiz)
                VALUES
                    (:bon_nr, 'verkauf', :kasse_id, :auftrag_id, :kunden_id, :zahlungsart,
                     :bruttobetrag, :gegeben, :rueckgeld, :bar_betrag, :karten_betrag,
                     :gutschein_code, :gutschein_betrag, :benutzer_id, :notiz)
            ");
            $stmt->execute([
                ':bon_nr'           => $bonNr,
                ':kasse_id'         => $kasseId,
                ':auftrag_id'       => $bonDaten['auftrag_id']       ?? null,
                ':kunden_id'        => $bonDaten['kunden_id']        ?? null,
                ':zahlungsart'      => $bonDaten['zahlungsart']      ?? 'bar',
                ':bruttobetrag'     => $bonDaten['bruttobetrag']     ?? 0,
                ':gegeben'          => $bonDaten['gegeben']          ?? null,
                ':rueckgeld'        => $bonDaten['rueckgeld']        ?? null,
                ':bar_betrag'       => $bonDaten['bar_betrag']       ?? null,
                ':karten_betrag'    => $bonDaten['karten_betrag']    ?? null,
                ':gutschein_code'   => $bonDaten['gutschein_code']   ?? null,
                ':gutschein_betrag' => $bonDaten['gutschein_betrag'] ?? null,
                ':benutzer_id'      => $benutzerId,
                ':notiz'            => $bonDaten['notiz']            ?? null,
            ]);
            $bonId = (int)$this->db->lastInsertId();

            $lagerSvc = new LagerService();
            foreach ($positionen as $i => $pos) {
                $stmt2 = $this->db->prepare("
                    INSERT INTO kassen_bon_positionen
                        (bon_id, block, artikel_id, bezeichnung, ean, menge,
                         einzelpreis_brutto, rabatt_prozent, steuer_prozent, charge, sort_order)
                    VALUES
                        (:bon_id, :block, :artikel_id, :bezeichnung, :ean, :menge,
                         :einzelpreis_brutto, :rabatt_prozent, :steuer_prozent, :charge, :sort)
                ");
                $stmt2->execute([
                    ':bon_id'             => $bonId,
                    ':block'              => $pos['block']              ?? null,
                    ':artikel_id'         => $pos['artikel_id']         ?? null,
                    ':bezeichnung'        => $pos['bezeichnung'],
                    ':ean'                => $pos['ean']                ?? null,
                    ':menge'              => $pos['menge']              ?? 1,
                    ':einzelpreis_brutto' => $pos['einzelpreis_brutto'] ?? 0,
                    ':rabatt_prozent'     => $pos['rabatt_prozent']     ?? 0,
                    ':steuer_prozent'     => $pos['steuer_prozent']     ?? 20,
                    ':charge'             => $pos['charge']             ?? null,
                    ':sort'               => $i,
                ]);

                // Lager ausbuchen — überspringen wenn Position individuell gesperrt
                // (abholbereit → Packplatz hat schon gebucht; nur_zahlung → Packplatz bucht später)
                if (!empty($pos['artikel_id']) && empty($pos['kein_lagerabzug'])) {
                    $artId    = (int)$pos['artikel_id'];
                    $posMenge = (float)($pos['menge'] ?? 1);
                    $kasseNr  = explode('-', $bonNr)[0]; // z.B. 'K1'

                    // Korrekturbuchung wenn Bestand nicht ausreicht
                    // (Artikel war physisch vorhanden, Systembestand war falsch)
                    $aktBestand = $this->getAktuellerBestand($artId, $lagerId);
                    if ($aktBestand < $posMenge) {
                        $korrMenge = $posMenge - max(0.0, $aktBestand);
                        $lagerSvc->wareneingang([
                            'artikel_id'  => $artId,
                            'lager_id'    => $lagerId,
                            'menge'       => $korrMenge,
                            'referenz'    => 'Korrekturbuchung ' . $kasseNr . ' – ' . $bonNr,
                            'notiz'       => 'Automatische Korrekturbuchung bei Kassenverkauf (Überverkauf bestätigt)',
                            'benutzer_id' => $benutzerId,
                        ]);
                    }

                    $lagerSvc->warenausgang([
                        'artikel_id'  => $artId,
                        'lager_id'    => $lagerId,
                        'menge'       => $posMenge,
                        'referenz'    => 'Kassenbon ' . $bonNr,
                        'benutzer_id' => $benutzerId,
                    ]);
                }
            }

            // Auftrag-Eintrag anlegen (kanal='kasse') → erscheint in auftraege/liste.php
            $netto = 0.0;
            $steuer = 0.0;
            foreach ($positionen as $p) {
                $zeileBrutto = (float)($p['menge'] ?? 1)
                    * (float)($p['einzelpreis_brutto'] ?? 0)
                    * (1 - (float)($p['rabatt_prozent'] ?? 0) / 100);
                $faktor   = 1 + ((float)($p['steuer_prozent'] ?? 0) / 100);
                $zeileNet = $faktor > 0 ? $zeileBrutto / $faktor : $zeileBrutto;
                $netto   += $zeileNet;
                $steuer  += $zeileBrutto - $zeileNet;
            }
            $aufZahlungsart = match($bonDaten['zahlungsart'] ?? 'bar') {
                'bar'       => 'bar',
                'gutschein' => 'gutschein',
                default     => 'gemischt',
            };
            $kundenSnapshot = $bonDaten['kunden_id']
                ? null
                : json_encode(['name' => 'Laufkunde', 'kundennummer' => '-'], JSON_UNESCAPED_UNICODE);
            $stmtAuf = $this->db->prepare("
                INSERT INTO auftraege
                    (auftrag_nr, kunden_id, kunden_snapshot, kanal,
                     zahlungsstatus, lieferstatus, zahlungsart,
                     nettobetrag, steuerbetrag, bruttobetrag,
                     bezahlt_am, versand_datum, lieferart, erstellt_von)
                VALUES
                    (:bon_nr, :kunden_id, :kunden_snapshot, 'kasse',
                     'bezahlt', 'abgeschlossen', :zahlungsart,
                     :netto, :steuer, :brutto,
                     NOW(), NOW(), 'abholung', :erstellt_von)
            ");
            $stmtAuf->execute([
                ':bon_nr'          => $bonNr,
                ':kunden_id'       => $bonDaten['kunden_id'] ?: null,
                ':kunden_snapshot' => $kundenSnapshot,
                ':zahlungsart'     => $aufZahlungsart,
                ':netto'           => round($netto, 2),
                ':steuer'          => round($steuer, 2),
                ':brutto'          => $bonDaten['bruttobetrag'] ?? 0,
                ':erstellt_von'    => $benutzerId,
            ]);
            $auftragId = (int)$this->db->lastInsertId();

            // Positionen in auftrag_positionen spiegeln
            // Divers (artikel_id=null) → Platzhalter-Artikel 99-9999, Bezeichnung bleibt frei
            $diversArtikelId = $this->getDiversArtikelId();
            $stmtPos = $this->db->prepare("
                INSERT INTO auftrag_positionen
                    (auftrag_id, artikel_id, bezeichnung, menge,
                     einzelpreis_netto, steuer_prozent, rabatt_prozent, gesamtpreis_netto)
                VALUES
                    (:auftrag_id, :artikel_id, :bezeichnung, :menge,
                     :einzelpreis_netto, :steuer_prozent, :rabatt_prozent, :gesamtpreis_netto)
            ");
            foreach ($positionen as $p) {
                $artIdPos  = !empty($p['artikel_id']) ? (int)$p['artikel_id'] : $diversArtikelId;
                if (!$artIdPos) continue; // 99-9999 nicht angelegt? überspringen
                $brutto    = (float)($p['einzelpreis_brutto'] ?? 0);
                $stProzent = (float)($p['steuer_prozent'] ?? 20);
                $faktor    = 1 + $stProzent / 100;
                $nettEP    = $faktor > 0 ? round($brutto / $faktor, 4) : $brutto;
                $menge     = (float)($p['menge'] ?? 1);
                $rabatt    = (float)($p['rabatt_prozent'] ?? 0);
                $gesNetto  = round($menge * $nettEP * (1 - $rabatt / 100), 2);
                $stmtPos->execute([
                    ':auftrag_id'         => $auftragId,
                    ':artikel_id'         => $artIdPos,
                    ':bezeichnung'        => $p['bezeichnung'],
                    ':menge'              => (int)$menge,
                    ':einzelpreis_netto'  => $nettEP,
                    ':steuer_prozent'     => $stProzent,
                    ':rabatt_prozent'     => $rabatt,
                    ':gesamtpreis_netto'  => $gesNetto,
                ]);
            }

            // Bon mit Auftrag verknüpfen
            $this->db->prepare("UPDATE kassen_bons SET auftrag_id = :aid WHERE id = :bid")
                ->execute([':aid' => $auftragId, ':bid' => $bonId]);

            Logger::log('kasse.bon.erstellt', 'kassen_bons', $bonId, [
                'bon_nr'       => $bonNr,
                'auftrag_id'   => $auftragId,
                'zahlungsart'  => $bonDaten['zahlungsart'] ?? 'bar',
                'bruttobetrag' => $bonDaten['bruttobetrag'] ?? 0,
            ], $benutzerId);

            $this->db->commit();
            return ['erfolg' => true, 'bon_id' => $bonId, 'bon_nr' => $bonNr];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => 'Datenbankfehler: ' . $e->getMessage()];
        }
    }

    /**
     * Storniert einen Bon: markiert Original als storniert, bucht Lagen zurück,
     * erstellt Storno-Bon mit negativen Beträgen.
     */
    public function storniereBon(int $bonId, int $benutzerId): array
    {
        $bon = $this->getBon($bonId);
        if (!$bon) return ['erfolg' => false, 'fehler' => 'Bon nicht gefunden.'];
        if ($bon['storniert']) return ['erfolg' => false, 'fehler' => 'Bon ist bereits storniert.'];
        if ($bon['typ'] !== 'verkauf') return ['erfolg' => false, 'fehler' => 'Nur Verkaufsbons können storniert werden.'];

        $kasse = $this->getKasse((int)$bon['kasse_id']);
        $lagerId = $kasse ? (int)$kasse['lager_id'] : 1;

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE kassen_bons SET storniert = 1 WHERE id = :id")
                ->execute([':id' => $bonId]);

            $stornoBonNr = $this->naechsteBonNr((int)$bon['kasse_id'], 'S');
            $stmt = $this->db->prepare("
                INSERT INTO kassen_bons
                    (bon_nr, typ, kasse_id, zahlungsart, bruttobetrag,
                     benutzer_id, storno_von_id, notiz)
                VALUES
                    (:bon_nr, 'storno', :kasse_id, :zahlungsart, :bruttobetrag,
                     :benutzer_id, :storno_von_id, :notiz)
            ");
            $stmt->execute([
                ':bon_nr'        => $stornoBonNr,
                ':kasse_id'      => $bon['kasse_id'],
                ':zahlungsart'   => $bon['zahlungsart'],
                ':bruttobetrag'  => -abs((float)$bon['bruttobetrag']),
                ':benutzer_id'   => $benutzerId,
                ':storno_von_id' => $bonId,
                ':notiz'         => 'Storno von ' . $bon['bon_nr'],
            ]);
            $stornoBonId = (int)$this->db->lastInsertId();

            $lagerSvc = new LagerService();
            foreach ($bon['positionen'] as $pos) {
                $stmt2 = $this->db->prepare("
                    INSERT INTO kassen_bon_positionen
                        (bon_id, artikel_id, bezeichnung, ean, menge,
                         einzelpreis_brutto, rabatt_prozent, steuer_prozent, charge)
                    VALUES
                        (:bon_id, :artikel_id, :bezeichnung, :ean, :menge,
                         :einzelpreis_brutto, :rabatt_prozent, :steuer_prozent, :charge)
                ");
                $stmt2->execute([
                    ':bon_id'             => $stornoBonId,
                    ':artikel_id'         => $pos['artikel_id'],
                    ':bezeichnung'        => $pos['bezeichnung'],
                    ':ean'                => $pos['ean'],
                    ':menge'              => -abs((float)$pos['menge']),
                    ':einzelpreis_brutto' => $pos['einzelpreis_brutto'],
                    ':rabatt_prozent'     => $pos['rabatt_prozent'],
                    ':steuer_prozent'     => $pos['steuer_prozent'],
                    ':charge'             => $pos['charge'],
                ]);

                if (!empty($pos['artikel_id'])) {
                    $lagerSvc->wareneingang([
                        'artikel_id'  => (int)$pos['artikel_id'],
                        'lager_id'    => $lagerId,
                        'menge'       => abs((float)$pos['menge']),
                        'charge'      => $pos['charge'] ?? null,
                        'referenz'    => 'Storno ' . $bon['bon_nr'],
                        'benutzer_id' => $benutzerId,
                    ]);
                }
            }

            Logger::log('kasse.bon.storniert', 'kassen_bons', $bonId, [
                'storno_bon_id' => $stornoBonId,
            ], $benutzerId);

            $this->db->commit();
            return ['erfolg' => true, 'storno_bon_id' => $stornoBonId, 'bon_nr' => $stornoBonNr];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => $e->getMessage()];
        }
    }

    // ── Bon-Nr generieren ─────────────────────────────────────────────────────

    public function naechsteBonNr(int $kasseId = 1, string $prefix = ''): string
    {
        $jahr = date('Y');
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM kassen_bons
            WHERE kasse_id = :kasse_id AND YEAR(erstellt_am) = :jahr
        ");
        $stmt->execute([':kasse_id' => $kasseId, ':jahr' => $jahr]);
        $n = (int)$stmt->fetchColumn() + 1;
        $kasse = $this->getKasse($kasseId);
        $nr = $kasse ? $kasse['kasse_nr'] : 'K' . $kasseId;
        return $nr . ($prefix ? '-' . $prefix : '') . '-' . $jahr . '-' . str_pad($n, 6, '0', STR_PAD_LEFT);
    }

    // ── Bon laden ─────────────────────────────────────────────────────────────

    public function getBon(int $bonId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT b.*, k.name AS kasse_name, k.kasse_nr,
                   u.formularname AS benutzer_name
            FROM kassen_bons b
            LEFT JOIN kassen k ON k.id = b.kasse_id
            LEFT JOIN benutzer u ON u.id = b.benutzer_id
            WHERE b.id = :id
        ");
        $stmt->execute([':id' => $bonId]);
        $bon = $stmt->fetch();
        if (!$bon) return null;

        $stmt2 = $this->db->prepare("
            SELECT * FROM kassen_bon_positionen WHERE bon_id = :bid ORDER BY sort_order, id
        ");
        $stmt2->execute([':bid' => $bonId]);
        $bon['positionen'] = $stmt2->fetchAll();

        return $bon;
    }

    // ── Bon-Journal ───────────────────────────────────────────────────────────

    public function getBonListe(int $kasseId, string $datum = '', int $limit = 100): array
    {
        $where = "WHERE b.kasse_id = :kasse_id";
        $params = [':kasse_id' => $kasseId];
        if ($datum) {
            $where .= " AND DATE(b.erstellt_am) = :datum";
            $params[':datum'] = $datum;
        }
        $stmt = $this->db->prepare("
            SELECT b.id, b.bon_nr, b.typ, b.zahlungsart, b.bruttobetrag,
                   b.storniert, b.erstellt_am, u.formularname AS benutzer_name
            FROM kassen_bons b
            LEFT JOIN benutzer u ON u.id = b.benutzer_id
            $where
            ORDER BY b.id DESC
            LIMIT $limit
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ── Kassenbuch ────────────────────────────────────────────────────────────

    public function bucheKassenbuch(string $typ, float $betrag, ?string $notiz, int $kasseId, int $benutzerId): array
    {
        if (!in_array($typ, ['einlage', 'entnahme', 'anfangsbestand'])) {
            return ['erfolg' => false, 'fehler' => 'Unbekannter Typ.'];
        }
        if ($betrag <= 0) {
            return ['erfolg' => false, 'fehler' => 'Betrag muss größer als 0 sein.'];
        }
        $stmt = $this->db->prepare("
            INSERT INTO kassenbuch (typ, betrag, notiz, kasse_id, benutzer_id)
            VALUES (:typ, :betrag, :notiz, :kasse_id, :benutzer_id)
        ");
        $stmt->execute([
            ':typ'         => $typ,
            ':betrag'      => $typ === 'entnahme' ? -abs($betrag) : abs($betrag),
            ':notiz'       => $notiz,
            ':kasse_id'    => $kasseId,
            ':benutzer_id' => $benutzerId,
        ]);
        Logger::log('kasse.kassenbuch.' . $typ, 'kassenbuch', (int)$this->db->lastInsertId(), [
            'betrag' => $betrag,
        ], $benutzerId);
        return ['erfolg' => true];
    }

    public function getKassenbuchHeute(int $kasseId): array
    {
        $stmt = $this->db->prepare("
            SELECT k.*, u.formularname AS benutzer_name
            FROM kassenbuch k
            LEFT JOIN benutzer u ON u.id = k.benutzer_id
            WHERE k.kasse_id = :kasse_id AND DATE(k.erstellt_am) = CURDATE()
            ORDER BY k.id DESC
        ");
        $stmt->execute([':kasse_id' => $kasseId]);
        return $stmt->fetchAll();
    }

    // ── Kassenstand ───────────────────────────────────────────────────────────

    /** Kassenstand = Summe aller Kassenbucheinträge + Barumsatz heute */
    public function getKassenstand(int $kasseId): float
    {
        $kb = $this->db->prepare("
            SELECT COALESCE(SUM(betrag), 0) FROM kassenbuch WHERE kasse_id = :kid
        ");
        $kb->execute([':kid' => $kasseId]);
        $kassenbuch = (float)$kb->fetchColumn();

        $bar = $this->db->prepare("
            SELECT COALESCE(SUM(
                CASE
                    WHEN zahlungsart = 'bar' THEN bruttobetrag
                    WHEN zahlungsart = 'kombi' THEN COALESCE(bar_betrag, 0)
                    ELSE 0
                END
            ), 0)
            FROM kassen_bons
            WHERE kasse_id = :kid AND typ = 'verkauf' AND storniert = 0
        ");
        $bar->execute([':kid' => $kasseId]);
        $barumsatz = (float)$bar->fetchColumn();

        return $kassenbuch + $barumsatz;
    }

    // ── X-Bon / Z-Bon ────────────────────────────────────────────────────────

    /** Tagesübersicht: Umsatz, Anzahl Bons, Aufschlüsselung nach Zahlungsart */
    public function getTagesKennzahlen(int $kasseId, ?string $datum = null): array
    {
        $datum = $datum ?: date('Y-m-d');
        $params = [':kid' => $kasseId, ':datum' => $datum];

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*)                                          AS anzahl_bons,
                COALESCE(SUM(bruttobetrag), 0)                   AS umsatz_gesamt,
                COALESCE(SUM(CASE WHEN zahlungsart = 'bar'           THEN bruttobetrag ELSE 0 END), 0) AS umsatz_bar,
                COALESCE(SUM(CASE WHEN zahlungsart = 'karte_extern'  THEN bruttobetrag ELSE 0 END), 0) AS umsatz_karte,
                COALESCE(SUM(CASE WHEN zahlungsart = 'gutschein'     THEN bruttobetrag ELSE 0 END), 0) AS umsatz_gs,
                COALESCE(SUM(CASE WHEN zahlungsart = 'kombi'         THEN COALESCE(bar_betrag, 0) ELSE 0 END), 0) AS umsatz_kombi_bar,
                COALESCE(SUM(CASE WHEN zahlungsart = 'kombi'         THEN COALESCE(karten_betrag, 0) ELSE 0 END), 0) AS umsatz_kombi_karte,
                COALESCE(SUM(CASE WHEN storniert = 1                 THEN bruttobetrag ELSE 0 END), 0) AS storniert_betrag,
                COUNT(CASE WHEN storniert = 1 THEN 1 END)         AS anzahl_stornos
            FROM kassen_bons
            WHERE kasse_id = :kid
              AND DATE(erstellt_am) = :datum
              AND typ = 'verkauf'
        ");
        $stmt->execute($params);
        $kennzahlen = $stmt->fetch();

        // Kassenbuch-Bewegungen heute
        $kb = $this->db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN typ = 'einlage'   THEN betrag ELSE 0 END), 0) AS einlagen,
                COALESCE(SUM(CASE WHEN typ = 'entnahme'  THEN betrag ELSE 0 END), 0) AS entnahmen
            FROM kassenbuch
            WHERE kasse_id = :kid AND DATE(erstellt_am) = :datum
        ");
        $kb->execute($params);
        $kassenbuch = $kb->fetch();

        return array_merge($kennzahlen, $kassenbuch, ['datum' => $datum]);
    }

    public function erstelleXBon(int $kasseId, int $benutzerId): array
    {
        $kennzahlen = $this->getTagesKennzahlen($kasseId);
        $bonNr = $this->naechsteBonNr($kasseId, 'X');
        $stmt = $this->db->prepare("
            INSERT INTO kassen_bons (bon_nr, typ, kasse_id, bruttobetrag, benutzer_id, notiz)
            VALUES (:bon_nr, 'x_bon', :kasse_id, :bruttobetrag, :benutzer_id, 'X-Bon Zwischenabschluss')
        ");
        $stmt->execute([
            ':bon_nr'       => $bonNr,
            ':kasse_id'     => $kasseId,
            ':bruttobetrag' => $kennzahlen['umsatz_gesamt'],
            ':benutzer_id'  => $benutzerId,
        ]);
        return ['erfolg' => true, 'bon_nr' => $bonNr, 'kennzahlen' => $kennzahlen];
    }

    public function erstelleZBon(int $kasseId, int $benutzerId): array
    {
        $kennzahlen = $this->getTagesKennzahlen($kasseId);
        $bonNr = $this->naechsteBonNr($kasseId, 'Z');
        $kassenstand = $this->getKassenstand($kasseId);
        $stmt = $this->db->prepare("
            INSERT INTO kassen_bons (bon_nr, typ, kasse_id, bruttobetrag, benutzer_id, notiz)
            VALUES (:bon_nr, 'z_bon', :kasse_id, :bruttobetrag, :benutzer_id, :notiz)
        ");
        $stmt->execute([
            ':bon_nr'       => $bonNr,
            ':kasse_id'     => $kasseId,
            ':bruttobetrag' => $kennzahlen['umsatz_gesamt'],
            ':benutzer_id'  => $benutzerId,
            ':notiz'        => 'Z-Bon Tagesabschluss ' . date('d.m.Y') . ' | Kassenstand: ' . number_format($kassenstand, 2, '.', ''),
        ]);
        Logger::log('kasse.z_bon', 'kassen_bons', (int)$this->db->lastInsertId(), $kennzahlen, $benutzerId);
        return ['erfolg' => true, 'bon_nr' => $bonNr, 'kennzahlen' => $kennzahlen, 'kassenstand' => $kassenstand];
    }

    // ── Offene Auswahl ────────────────────────────────────────────────────────

    public function getOffeneAuswahl(string $status = 'offen'): array
    {
        $stmt = $this->db->prepare("
            SELECT oa.*, u.formularname AS benutzer_name
            FROM offene_auswahl oa
            LEFT JOIN benutzer u ON u.id = oa.benutzer_id
            WHERE oa.status = :status
            ORDER BY oa.ausgegeben_am DESC
        ");
        $stmt->execute([':status' => $status]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['positionen'] = json_decode($r['positionen'], true) ?: [];
        }
        return $rows;
    }

    public function erstelleMitgeben(array $daten, array $positionen, int $benutzerId): array
    {
        if (empty($positionen)) return ['erfolg' => false, 'fehler' => 'Keine Artikel.'];

        $lagerId = (int)($daten['lager_id'] ?? 1);
        $lagerSvc = new LagerService();

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO offene_auswahl
                    (kunden_name, kunden_id, lager_id, positionen, rueckgabe_bis, notiz, benutzer_id)
                VALUES
                    (:kunden_name, :kunden_id, :lager_id, :positionen, :rueckgabe_bis, :notiz, :benutzer_id)
            ");
            $stmt->execute([
                ':kunden_name'   => $daten['kunden_name']   ?? null,
                ':kunden_id'     => $daten['kunden_id']     ?? null,
                ':lager_id'      => $lagerId,
                ':positionen'    => json_encode($positionen),
                ':rueckgabe_bis' => $daten['rueckgabe_bis'] ?? null,
                ':notiz'         => $daten['notiz']         ?? null,
                ':benutzer_id'   => $benutzerId,
            ]);
            $oaId = (int)$this->db->lastInsertId();

            foreach ($positionen as $pos) {
                if (!empty($pos['artikel_id'])) {
                    $lagerSvc->warenausgang([
                        'artikel_id'  => (int)$pos['artikel_id'],
                        'lager_id'    => $lagerId,
                        'menge'       => (float)($pos['menge'] ?? 1),
                        'referenz'    => 'Offene Auswahl #' . $oaId,
                        'benutzer_id' => $benutzerId,
                    ]);
                }
            }

            $this->db->commit();
            return ['erfolg' => true, 'oa_id' => $oaId];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => $e->getMessage()];
        }
    }

    public function offeneAuswahlZurueck(int $oaId, int $benutzerId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM offene_auswahl WHERE id = :id AND status = 'offen'");
        $stmt->execute([':id' => $oaId]);
        $oa = $stmt->fetch();
        if (!$oa) return ['erfolg' => false, 'fehler' => 'Nicht gefunden oder bereits abgeschlossen.'];

        $lagerSvc = new LagerService();
        $positionen = json_decode($oa['positionen'], true) ?: [];
        foreach ($positionen as $pos) {
            if (!empty($pos['artikel_id'])) {
                $lagerSvc->wareneingang([
                    'artikel_id'  => (int)$pos['artikel_id'],
                    'lager_id'    => (int)$oa['lager_id'],
                    'menge'       => (float)($pos['menge'] ?? 1),
                    'charge'      => $pos['charge'] ?? null,
                    'referenz'    => 'Rückgabe Offene Auswahl #' . $oaId,
                    'benutzer_id' => $benutzerId,
                ]);
            }
        }
        $this->db->prepare("UPDATE offene_auswahl SET status = 'zurueck' WHERE id = :id")
            ->execute([':id' => $oaId]);

        return ['erfolg' => true];
    }

    /** Aktuellen Lagerbestand eines Artikels in einem Lager holen (für Korrekturbuchungs-Prüfung). */
    private function getAktuellerBestand(int $artikelId, int $lagerId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(bestand), 0)
            FROM lagerbestand
            WHERE artikel_id = :aid AND lager_id = :lid
        ");
        $stmt->execute([':aid' => $artikelId, ':lid' => $lagerId]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Teilrückgabe einer offenen Auswahl.
     * $rueckgabe: [{artikel_id, menge}] — nur was zurückkommt.
     * Artikel die nicht in $rueckgabe sind gelten als gekauft → werden zu einem Bon.
     */
    public function offeneAuswahlTeilrueckgabe(int $oaId, array $rueckgabe, int $benutzerId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM offene_auswahl WHERE id = :id AND status = 'offen'");
        $stmt->execute([':id' => $oaId]);
        $oa = $stmt->fetch();
        if (!$oa) return ['erfolg' => false, 'fehler' => 'Nicht gefunden oder bereits abgeschlossen.'];

        $positionen = json_decode($oa['positionen'], true) ?: [];
        $lagerId    = (int)$oa['lager_id'];
        $lagerSvc   = new LagerService();

        // Rückgabe-Index: artikel_id → zurückgekommen Menge
        $rueckIdx = [];
        foreach ($rueckgabe as $r) {
            $rueckIdx[(int)$r['artikel_id']] = (float)$r['menge'];
        }

        $this->db->beginTransaction();
        try {
            $bonPositionen = [];
            foreach ($positionen as $pos) {
                $artId      = (int)($pos['artikel_id'] ?? 0);
                $mengaRaus  = (float)($pos['menge'] ?? 1);
                $mengeRueck = $rueckIdx[$artId] ?? 0.0;
                $mengeKauf  = $mengaRaus - $mengeRueck;

                // Was zurückkommt → Lager zurückbuchen
                if ($artId > 0 && $mengeRueck > 0) {
                    $lagerSvc->wareneingang([
                        'artikel_id'  => $artId,
                        'lager_id'    => $lagerId,
                        'menge'       => $mengeRueck,
                        'charge'      => $pos['charge'] ?? null,
                        'referenz'    => 'Teilrückgabe Offene Auswahl #' . $oaId,
                        'benutzer_id' => $benutzerId,
                    ]);
                }

                // Was behalten wird → Bon-Position
                if ($mengeKauf > 0) {
                    $bonPositionen[] = array_merge($pos, ['menge' => $mengeKauf, 'block' => 'addon']);
                }
            }

            $this->db->prepare("UPDATE offene_auswahl SET status = 'gekauft' WHERE id = :id")
                ->execute([':id' => $oaId]);

            $this->db->commit();

            $result = ['erfolg' => true, 'oa_id' => $oaId];
            if (!empty($bonPositionen)) {
                $result['bon_positionen'] = $bonPositionen;
                $result['hinweis'] = 'Verkaufte Artikel als Bon abschließen.';
            }
            return $result;

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => $e->getMessage()];
        }
    }

    /**
     * Schwund-Buchung: Artikel abschreiben ohne Verkauf (Verlust, Beschädigung, Messe-Differenz).
     * $positionen: [{artikel_id, menge, charge?}]
     */
    public function schwundBuchen(array $positionen, string $grund, int $lagerId, int $benutzerId): array
    {
        if (empty($positionen)) return ['erfolg' => false, 'fehler' => 'Keine Positionen.'];

        $lagerSvc = new LagerService();
        $this->db->beginTransaction();
        try {
            foreach ($positionen as $pos) {
                if (empty($pos['artikel_id']) || empty($pos['menge'])) continue;
                $lagerSvc->warenausgang([
                    'artikel_id'  => (int)$pos['artikel_id'],
                    'lager_id'    => $lagerId,
                    'menge'       => (float)$pos['menge'],
                    'charge'      => $pos['charge'] ?? null,
                    'referenz'    => 'Schwund: ' . $grund,
                    'benutzer_id' => $benutzerId,
                ]);
            }
            Logger::log('kasse.schwund', 'lagerbestand', 0, [
                'grund'     => $grund,
                'lager_id'  => $lagerId,
                'positionen' => count($positionen),
            ], $benutzerId);
            $this->db->commit();
            return ['erfolg' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => $e->getMessage()];
        }
    }

    /** ID des Platzhalter-Artikels 99-9999 (Diverses) für auftrag_positionen. */
    private function getDiversArtikelId(): int
    {
        $stmt = $this->db->prepare("SELECT id FROM artikel WHERE artikelnummer = '99-9999' LIMIT 1");
        $stmt->execute();
        return (int)($stmt->fetchColumn() ?: 0);
    }

    // ── Schnellwahl ───────────────────────────────────────────────────────────

    public function getSchnellwahl(int $kasseId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT s.slot, s.artikel_id, s.label,
                       a.name              AS artikel_name,
                       a.artikelnummer,
                       COALESCE(s.label, a.name) AS anzeige_name,
                       COALESCE(
                           (SELECT ap.brutto_vk
                            FROM artikel_preise ap
                            INNER JOIN kundengruppen kg ON kg.id = ap.kundengruppen_id AND kg.ist_standard = 1
                            WHERE ap.artikel_id = a.id
                              AND (ap.gueltig_ab IS NULL OR ap.gueltig_ab <= CURDATE())
                              AND (ap.gueltig_bis IS NULL OR ap.gueltig_bis >= CURDATE())
                            ORDER BY ap.gueltig_ab DESC LIMIT 1
                           ), 0
                       ) AS brutto_vk,
                       (SELECT ac.code FROM artikel_codes ac
                        WHERE ac.artikel_id = a.id AND ac.typ = 'GTIN13' LIMIT 1
                       ) AS ean,
                       sk.satz AS steuer_prozent
                FROM kassen_schnellwahl s
                LEFT JOIN artikel a ON a.id = s.artikel_id
                LEFT JOIN steuerklassen sk ON sk.id = a.steuerklasse_id
                WHERE s.kasse_id = :kasse_id
                ORDER BY s.slot
            ");
            $stmt->execute([':kasse_id' => $kasseId]);
            $rows = $stmt->fetchAll();
            $result = [];
            foreach ($rows as $r) {
                $result[(int)$r['slot']] = $r;
            }
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function setSchnellwahl(int $kasseId, int $slot, ?int $artikelId, ?string $label): bool
    {
        if ($artikelId === null) {
            $stmt = $this->db->prepare("DELETE FROM kassen_schnellwahl WHERE kasse_id = :kid AND slot = :slot");
            $stmt->execute([':kid' => $kasseId, ':slot' => $slot]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO kassen_schnellwahl (kasse_id, slot, artikel_id, label)
                VALUES (:kid, :slot, :aid, :label)
                ON DUPLICATE KEY UPDATE artikel_id = :aid2, label = :label2
            ");
            $stmt->execute([
                ':kid'    => $kasseId,
                ':slot'   => $slot,
                ':aid'    => $artikelId,
                ':label'  => $label ?: null,
                ':aid2'   => $artikelId,
                ':label2' => $label ?: null,
            ]);
        }
        return true;
    }

    // ── Artikel-Textsuche (für Kasse) ─────────────────────────────────────────

    public function sucheArtikel(string $suche, int $lagerId, int $limit = 20): array
    {
        $q = '%' . $suche . '%';
        $stmt = $this->db->prepare("
            SELECT
                a.id,
                a.name              AS bezeichnung,
                a.artikelnummer,
                a.ist_vater,
                a.ueberverkauf_erlaubt,
                sk.satz             AS steuer_prozent,
                COALESCE(
                    (SELECT ap.brutto_vk
                     FROM artikel_preise ap
                     INNER JOIN kundengruppen kg ON kg.id = ap.kundengruppen_id AND kg.ist_standard = 1
                     WHERE ap.artikel_id = a.id
                       AND (ap.gueltig_ab IS NULL OR ap.gueltig_ab <= CURDATE())
                       AND (ap.gueltig_bis IS NULL OR ap.gueltig_bis >= CURDATE())
                     ORDER BY ap.gueltig_ab DESC LIMIT 1
                    ), 0
                )                   AS brutto_vk,
                COALESCE(
                    (SELECT SUM(lb.bestand)
                     FROM lagerbestand lb
                     WHERE lb.artikel_id = a.id AND lb.lager_id = :lager_id
                    ), 0
                )                   AS bestand_physisch,
                (SELECT ac.code FROM artikel_codes ac
                 WHERE ac.artikel_id = a.id AND ac.typ = 'GTIN13' LIMIT 1
                )                   AS ean
            FROM artikel a
            LEFT JOIN steuerklassen sk ON sk.id = a.steuerklasse_id
            WHERE a.aktiv = 1
              AND (
                  a.name LIKE :q
                  OR a.artikelnummer LIKE :q2
                  OR EXISTS (
                      SELECT 1 FROM artikel_codes ac
                      WHERE ac.artikel_id = a.id AND ac.code LIKE :q3
                  )
              )
            ORDER BY a.name
            LIMIT :lmt
        ");
        $stmt->execute([
            ':q'        => $q,
            ':q2'       => $q,
            ':q3'       => $q,
            ':lager_id' => $lagerId,
            ':lmt'      => $limit,
        ]);
        return $stmt->fetchAll();
    }
}
