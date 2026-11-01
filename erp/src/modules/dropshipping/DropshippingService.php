<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../auftraege/AuftragRepository.php';
require_once __DIR__ . '/../auftraege/AuftragAbschluss.php';
require_once __DIR__ . '/../auftraege/VersandMail.php';
require_once __DIR__ . '/../dokumente/DokumentService.php';
require_once __DIR__ . '/../dokumente/RechnungMailService.php';
require_once __DIR__ . '/../bestellungen/BestellungService.php';

/**
 * Dropshipping (Streckengeschäft): Lieferant schickt direkt an den Endkunden.
 *
 * Regeln (Jacky 2026-10-31):
 *  - Strecke nur, wenn der GANZE Auftrag bei EINEM Lieferanten dropship-fähig ist —
 *    sonst normal bestellen und gemeinsam verschicken (keine gemischten Aufträge)
 *  - bestellt wird von Hand im Händlerportal des Lieferanten (kein PDF/Mail an ihn),
 *    die Dropship-Bestellung im ERP hält EK + Pauschale fest (= spätere Lieferanten-Rechnung)
 *  - Versandmail des Lieferanten kommt an uns → "Versendet + Tracking" hier eintragen:
 *    Lieferung OHNE Lagerbuchung, dann Rechnung + Versandmail an den Kunden wie beim Packplatz
 */
class DropshippingService
{
    private PDO $db;
    private AuftragRepository $auftragRepo;

    public function __construct()
    {
        $this->db          = Database::getInstance();
        $this->auftragRepo = new AuftragRepository();
    }

    /**
     * Prüft, ob ein Auftrag per Strecke gehen kann.
     * Rückgabe: moeglich, grund (warum nicht), bereit (bezahlt bzw. Zahlungsart Rechnung),
     *           lieferant, positionen (mit Lieferanten-Artikelnummer + EK), bestellung (falls schon bestellt)
     */
    public function pruefen(int $auftragId): array
    {
        $erg = ['moeglich' => false, 'grund' => null, 'bereit' => false,
                'lieferant' => null, 'positionen' => [], 'bestellung' => null];

        $auftrag = $this->ladeAuftrag($auftragId);
        if (!$auftrag) {
            $erg['grund'] = 'Auftrag nicht gefunden.';
            return $erg;
        }

        $erg['bestellung'] = $this->bestellungZuAuftrag($auftragId);
        if ($erg['bestellung']) {
            $erg['grund'] = 'Bereits per Dropshipping bestellt.';
            return $erg;
        }

        if ($auftrag['kanal'] === 'kasse')                 { $erg['grund'] = 'Kassen-Auftrag.'; return $erg; }
        if ($auftrag['lieferart'] !== 'versand')           { $erg['grund'] = 'Nur bei Versand möglich (nicht bei Abholung).'; return $erg; }
        if ($auftrag['zahlungsart'] === 'nachnahme')       { $erg['grund'] = 'Nachnahme geht nicht per Strecke.'; return $erg; }
        if (in_array($auftrag['zahlungsstatus'], ['storniert', 'erstattet'], true)
            || !in_array($auftrag['lieferstatus'], ['neu', 'in_bearbeitung', 'versandbereit', 'zurueckgestellt'], true)) {
            $erg['grund'] = 'Auftrag ist nicht mehr offen.';
            return $erg;
        }

        $positionen = $this->positionen($auftragId);
        if (empty($positionen)) { $erg['grund'] = 'Keine Positionen.'; return $erg; }
        foreach ($positionen as $p) {
            if ((int)$p['menge_geliefert'] > 0 || (int)$p['menge_abgeholt'] > 0) {
                $erg['grund'] = 'Es wurde schon etwas ausgeliefert.';
                return $erg;
            }
            if ((int)$p['ist_gutschein'] === 1) {
                $erg['grund'] = 'Auftrag enthält einen Gutschein.';
                return $erg;
            }
        }

        // Dropship-fähige Zuordnungen je Artikel (Kind ohne eigene Zuordnung → die des Vaters)
        $zuordnungen = $this->dropshipZuordnungen($positionen);

        // Lieferant suchen, der ALLE Positionen abdeckt
        $kandidaten = null;
        foreach ($positionen as $p) {
            $ids = array_keys($zuordnungen[$p['artikel_id']] ?? []);
            $kandidaten = $kandidaten === null ? $ids : array_values(array_intersect($kandidaten, $ids));
            if (empty($kandidaten)) {
                $erg['grund'] = '„' . $p['bezeichnung'] . '“ ist nicht per Dropshipping lieferbar.';
                return $erg;
            }
        }
        $lieferantId = (int)$kandidaten[0];

        foreach ($positionen as &$p) {
            $z = $zuordnungen[$p['artikel_id']][$lieferantId];
            $p['artikelnummer_lieferant'] = $z['artikelnummer_lieferant'];
            $p['netto_ek']                = $z['netto_ek'];
        }
        unset($p);

        $stmt = $this->db->prepare("SELECT id, name, firma, kundennummer, dropship_pauschale FROM lieferanten WHERE id = ?");
        $stmt->execute([$lieferantId]);

        $erg['moeglich']   = true;
        $erg['bereit']     = $auftrag['zahlungsstatus'] === 'bezahlt' || $auftrag['zahlungsart'] === 'rechnung';
        $erg['lieferant']  = $stmt->fetch(PDO::FETCH_ASSOC);
        $erg['positionen'] = $positionen;
        return $erg;
    }

    /**
     * Legt die Dropship-Bestellung an (Bestellung im Portal macht der Benutzer selbst).
     * Reservierungen des Auftrags werden geschlossen — die Ware kommt nicht aus unserem Lager.
     */
    public function bestellen(int $auftragId, int $benutzerId, ?float $pauschale = null): array
    {
        $pruefung = $this->pruefen($auftragId);
        if (!$pruefung['moeglich']) {
            return ['erfolg' => false, 'fehler' => $pruefung['grund']];
        }
        $auftrag   = $this->ladeAuftrag($auftragId);
        $lieferant = $pruefung['lieferant'];
        $pauschale ??= $lieferant['dropship_pauschale'] !== null ? (float)$lieferant['dropship_pauschale'] : null;

        // Erste Adresse mit Straße nehmen (Lieferadresse kann leer angelegt sein)
        $lieferadresse = null;
        foreach (['lieferadresse_snapshot', 'rechnungsadresse_snapshot', 'kunden_snapshot'] as $feld) {
            $adr = json_decode($auftrag[$feld] ?? '', true);
            if (!empty($adr['strasse'])) { $lieferadresse = $auftrag[$feld]; break; }
        }
        if ($lieferadresse === null) {
            return ['erfolg' => false, 'fehler' => 'Der Auftrag hat keine vollständige Lieferadresse.'];
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                INSERT INTO bestellungen
                    (lieferant_id, typ, auftrag_id, lieferadresse_snapshot, dropship_pauschale,
                     status, bestelldatum, zahlungsart, notiz, benutzer_id)
                VALUES (?, 'dropshipping', ?, ?, ?, 'offen', CURDATE(), 'rechnung', ?, ?)
            ")->execute([
                $lieferant['id'], $auftragId, $lieferadresse, $pauschale,
                'Dropshipping für Auftrag ' . $auftrag['auftrag_nr'], $benutzerId,
            ]);
            $bestellungId = (int)$this->db->lastInsertId();

            $stmtPos = $this->db->prepare("
                INSERT INTO bestellung_positionen (bestellung_id, artikel_id, menge_bestellt, ek_preis)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($pruefung['positionen'] as $p) {
                $stmtPos->execute([$bestellungId, $p['artikel_id'], $p['menge'], $p['netto_ek']]);
            }

            $this->auftragRepo->schliesseReservierungen($auftragId);

            $neuerStatus = $auftrag['lieferstatus'] === 'neu' ? 'in_bearbeitung' : $auftrag['lieferstatus'];
            $this->db->prepare("UPDATE auftraege SET lieferstatus = ?, aktualisiert_am = NOW() WHERE id = ?")
                     ->execute([$neuerStatus, $auftragId]);
            $this->auftragRepo->logStatus(
                $auftragId,
                $neuerStatus !== $auftrag['lieferstatus'] ? ['lieferstatus' => [$auftrag['lieferstatus'], $neuerStatus]] : [],
                'Per Dropshipping bei ' . $lieferant['name'] . ' bestellt (' . self::bestellnummer($bestellungId) . ')',
                $benutzerId
            );

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => 'Fehler beim Anlegen: ' . $e->getMessage()];
        }

        Logger::log('dropshipping.bestellt', 'bestellungen', $bestellungId, [
            'auftrag_id' => $auftragId, 'lieferant_id' => $lieferant['id'], 'pauschale' => $pauschale,
        ]);
        return ['erfolg' => true, 'bestellung_id' => $bestellungId];
    }

    /**
     * Storniert eine noch nicht versendete Dropship-Bestellung (z.B. Lang kann nicht liefern).
     * Die Reservierungen leben wieder auf — der Auftrag geht dann normal über den Packplatz.
     */
    public function stornieren(int $bestellungId, int $benutzerId): array
    {
        $b = $this->ladeBestellung($bestellungId);
        if (!$b || $b['status'] !== 'offen') {
            return ['erfolg' => false, 'fehler' => 'Nur offene Dropship-Bestellungen können storniert werden.'];
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE bestellungen SET status = 'storniert' WHERE id = ?")->execute([$bestellungId]);
            $this->db->prepare("
                UPDATE reservierungen SET status = 'offen'
                WHERE referenz_tabelle = 'auftraege' AND referenz_id = ? AND status = 'erledigt'
            ")->execute([$b['auftrag_id']]);
            $this->auftragRepo->logStatus((int)$b['auftrag_id'], [],
                'Dropshipping-Bestellung ' . self::bestellnummer($bestellungId, $b['bestelldatum']) . ' storniert — Auftrag geht wieder normal über den Packplatz',
                $benutzerId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => 'Fehler beim Stornieren: ' . $e->getMessage()];
        }

        Logger::log('dropshipping.storniert', 'bestellungen', $bestellungId, ['auftrag_id' => $b['auftrag_id']]);
        return ['erfolg' => true];
    }

    /**
     * Lieferant hat versendet: Lieferung ohne Lagerbuchung, Auftrag versendet, Rechnung + Versandmail.
     * Rückgabe zusätzlich: rechnung (rechnung_nr, dateiname) und mail_ok für den Hinweis an den Benutzer.
     */
    public function versendetMelden(int $bestellungId, string $tracking, string $versanddienstleister, int $benutzerId): array
    {
        $tracking = trim($tracking);
        if ($tracking === '') {
            return ['erfolg' => false, 'fehler' => 'Trackingnummer fehlt.'];
        }
        if (!array_key_exists($versanddienstleister, VersandMail::DIENSTLEISTER)) {
            $versanddienstleister = 'manuell';
        }

        $b = $this->ladeBestellung($bestellungId);
        if (!$b || $b['status'] !== 'offen') {
            return ['erfolg' => false, 'fehler' => 'Dropship-Bestellung nicht gefunden oder nicht mehr offen.'];
        }
        $auftragId = (int)$b['auftrag_id'];
        $auftrag   = $this->ladeAuftrag($auftragId);

        $positionen = [];
        $this->db->beginTransaction();
        try {
            // Alles Offene des Auftrags gilt als geliefert (Strecke = immer der ganze Auftrag)
            $stmt = $this->db->prepare("
                SELECT p.id, p.artikel_id, p.bezeichnung, p.menge - p.menge_geliefert AS rest,
                       a.artikelnummer, e.kuerzel AS einheit
                FROM auftrag_positionen p
                LEFT JOIN artikel a ON a.id = p.artikel_id
                LEFT JOIN einheiten e ON e.id = a.einheit_id
                WHERE p.auftrag_id = ? AND p.menge - p.menge_geliefert > 0
                ORDER BY p.sort_order, p.id
            ");
            $stmt->execute([$auftragId]);
            $positionen = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->db->prepare("
                UPDATE auftraege
                SET tracking_nr = ?, versanddienstleister = ?, versand_tracking = ?, versand_datum = NOW(),
                    lieferstatus = 'versendet', aktualisiert_am = NOW()
                WHERE id = ?
            ")->execute([$tracking, $versanddienstleister, $tracking, $auftragId]);

            $this->db->prepare("
                INSERT INTO auftrag_lieferungen
                    (auftrag_id, bestellung_id, tracking_nr, versanddienstleister, ist_teillieferung, benutzer_id)
                VALUES (?, ?, ?, ?, 0, ?)
            ")->execute([$auftragId, $bestellungId, $tracking, $versanddienstleister, $benutzerId]);
            $lieferungId = (int)$this->db->lastInsertId();

            $stmtLp  = $this->db->prepare("INSERT INTO auftrag_lieferung_positionen (lieferung_id, auftrag_position_id, menge) VALUES (?, ?, ?)");
            $stmtGel = $this->db->prepare("UPDATE auftrag_positionen SET menge_geliefert = menge_geliefert + ? WHERE id = ?");
            foreach ($positionen as $p) {
                $stmtLp->execute([$lieferungId, $p['id'], $p['rest']]);
                $stmtGel->execute([$p['rest'], $p['id']]);
            }

            // Bestellung erledigt — eingegangen = bestellt (nur Anzeige, keine Lagerbewegung)
            $this->db->prepare("UPDATE bestellungen SET status = 'erledigt' WHERE id = ?")->execute([$bestellungId]);
            $this->db->prepare("UPDATE bestellung_positionen SET menge_eingegangen = menge_bestellt WHERE bestellung_id = ? AND gestrichen = 0")
                     ->execute([$bestellungId]);

            $this->auftragRepo->schliesseReservierungen($auftragId);
            $this->auftragRepo->logStatus($auftragId, ['lieferstatus' => [$auftrag['lieferstatus'], 'versendet']],
                'Versendet per Dropshipping (' . self::bestellnummer($bestellungId, $b['bestelldatum']) . ') — Tracking: ' . $tracking, $benutzerId);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => 'Fehler beim Speichern: ' . $e->getMessage()];
        }

        Logger::log('dropshipping.versendet', 'auftraege', $auftragId, [
            'bestellung_id' => $bestellungId, 'tracking' => $tracking,
        ]);

        // Rechnung für genau diese Lieferung (wie Packplatz) — Fehler hier machen den Versand nicht rückgängig
        $erg = ['erfolg' => true, 'rechnung' => null, 'mail_ok' => false, 'hinweis' => null];
        try {
            $rgn = (new DokumentService())->erstelleRechnung($auftragId, $benutzerId, null, $lieferungId);
            if ($rgn['erfolg']) {
                $erg['rechnung'] = $rgn;
            } elseif (empty($rgn['nichts_offen'])) {
                $erg['hinweis'] = 'Rechnung konnte nicht erstellt werden: ' . ($rgn['fehler'] ?? '?');
            }
        } catch (Throwable $e) {
            error_log('[DropshipRechnung] Fehler: ' . $e->getMessage());
            $erg['hinweis'] = 'Rechnung konnte nicht erstellt werden: ' . $e->getMessage();
        }
        AuftragAbschluss::pruefe($auftragId, $benutzerId);

        // Versandmail (Lang verschickt in seinem Layout, deshalb kein eigener Lieferschein im Anhang)
        $mailPositionen = array_map(fn($p) => [
            'artikel_id'      => $p['artikel_id'],
            'bezeichnung'     => $p['bezeichnung'],
            'artikelnummer'   => $p['artikelnummer'] ?? '',
            'menge_versendet' => $p['rest'],
            'einheit'         => $p['einheit'] ?? 'Stk.',
        ], $positionen);
        VersandMail::sende($this->ladeAuftrag($auftragId), $tracking, $versanddienstleister, false, $mailPositionen);

        if ($erg['rechnung']) {
            try {
                $erg['mail_ok'] = RechnungMailService::sende((int)$erg['rechnung']['rechnung_id']);
            } catch (Throwable $e) {
                error_log('[RechnungMail] Fehler: ' . $e->getMessage());
            }
        }
        return $erg;
    }

    /**
     * Aufträge für den Listen-Filter "Strecke bereit": per Strecke möglich, noch nicht bestellt
     * und bezahlt bzw. Zahlungsart Rechnung. SQL sucht grob vor (offen, Versand, mindestens eine
     * Position bei einem Dropship-Lieferanten), pruefen() entscheidet je Auftrag.
     */
    public function streckeBereitIds(): array
    {
        $kandidaten = $this->db->query("
            SELECT DISTINCT a.id
            FROM auftraege a
            JOIN auftrag_positionen p ON p.auftrag_id = a.id
            JOIN artikel ar ON ar.id = p.artikel_id
            JOIN artikel_lieferanten al ON al.artikel_id IN (ar.id, ar.vaterartikel_id)
            JOIN lieferanten l ON l.id = al.lieferant_id
            WHERE a.lieferstatus IN ('neu','in_bearbeitung','versandbereit','zurueckgestellt')
              AND a.zahlungsstatus NOT IN ('storniert','erstattet')
              AND a.lieferart = 'versand' AND a.kanal <> 'kasse'
              AND COALESCE(al.dropshipping, l.dropshipping_aktiv) = 1
              AND NOT EXISTS (SELECT 1 FROM bestellungen b
                              WHERE b.auftrag_id = a.id AND b.typ = 'dropshipping' AND b.status <> 'storniert')
        ")->fetchAll(PDO::FETCH_COLUMN);

        $ids = [];
        foreach ($kandidaten as $id) {
            $p = $this->pruefen((int)$id);
            if ($p['moeglich'] && $p['bereit']) $ids[] = (int)$id;
        }
        return $ids;
    }

    /** Aufträge mit offener Dropship-Bestellung — bestellt, Tracking vom Lieferanten fehlt noch. */
    public function streckeBestelltIds(): array
    {
        return array_map('intval', $this->db->query("
            SELECT auftrag_id FROM bestellungen
            WHERE typ = 'dropshipping' AND status = 'offen' AND auftrag_id IS NOT NULL
        ")->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Nicht stornierte Dropship-Bestellung eines Auftrags (es gibt höchstens eine). */
    public function bestellungZuAuftrag(int $auftragId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT b.*, l.name AS lieferant_name
            FROM bestellungen b JOIN lieferanten l ON l.id = b.lieferant_id
            WHERE b.auftrag_id = ? AND b.typ = 'dropshipping' AND b.status <> 'storniert'
            ORDER BY b.id DESC LIMIT 1
        ");
        $stmt->execute([$auftragId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Positionen einer Dropship-Bestellung mit allem, was man im Portal braucht. */
    public function bestellPositionen(int $bestellungId): array
    {
        $stmt = $this->db->prepare("
            SELECT bp.artikel_id, bp.menge_bestellt, bp.ek_preis,
                   a.artikelnummer,
                   (SELECT ac.code FROM artikel_codes ac WHERE ac.artikel_id = a.id AND ac.typ IN ('GTIN13','GTIN8') LIMIT 1) AS ean,
                   a.name AS artikel_name,
                   COALESCE(al.artikelnummer_lieferant, alv.artikelnummer_lieferant) AS artikelnummer_lieferant
            FROM bestellung_positionen bp
            JOIN bestellungen b ON b.id = bp.bestellung_id
            JOIN artikel a ON a.id = bp.artikel_id
            LEFT JOIN artikel_lieferanten al  ON al.artikel_id  = a.id               AND al.lieferant_id  = b.lieferant_id
            LEFT JOIN artikel_lieferanten alv ON alv.artikel_id = a.vaterartikel_id AND alv.lieferant_id = b.lieferant_id
            WHERE bp.bestellung_id = ? AND bp.gestrichen = 0
            ORDER BY bp.id
        ");
        $stmt->execute([$bestellungId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Gleiches Schema wie normale Bestellungen (BE-), damit Kreditoren-Liste und Rechnung zusammenpassen. */
    public static function bestellnummer(int $id, ?string $datum = null): string
    {
        return BestellungService::bestellnummer($id, $datum ?? date('Y-m-d'));
    }

    // ── intern ────────────────────────────────────────────────────────────────

    private function ladeAuftrag(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM auftraege WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function ladeBestellung(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM bestellungen WHERE id = ? AND typ = 'dropshipping'");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function positionen(int $auftragId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.artikel_id, p.bezeichnung, p.menge, p.menge_geliefert, p.menge_abgeholt,
                   a.artikelnummer, a.vaterartikel_id, a.ist_gutschein
            FROM auftrag_positionen p
            JOIN artikel a ON a.id = p.artikel_id
            WHERE p.auftrag_id = ? AND p.menge > 0
            ORDER BY p.sort_order, p.id
        ");
        $stmt->execute([$auftragId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * [artikel_id => [lieferant_id => zuordnung]] — nur aktive, dropship-fähige Zuordnungen.
     * Kind-Artikel ohne eigene Zuordnung bei diesem Lieferanten erben die des Vaters.
     */
    private function dropshipZuordnungen(array $positionen): array
    {
        $ids = [];
        foreach ($positionen as $p) {
            $ids[] = (int)$p['artikel_id'];
            if ($p['vaterartikel_id']) $ids[] = (int)$p['vaterartikel_id'];
        }
        $ids = array_values(array_unique($ids));
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            SELECT al.artikel_id, al.lieferant_id, al.artikelnummer_lieferant, al.netto_ek,
                   COALESCE(al.dropshipping, l.dropshipping_aktiv) AS ds
            FROM artikel_lieferanten al
            JOIN lieferanten l ON l.id = al.lieferant_id
            WHERE al.artikel_id IN ($ph) AND COALESCE(al.aktiv, 1) = 1 AND COALESCE(l.aktiv, 1) = 1
        ");
        $stmt->execute($ids);
        $roh = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $z) {
            $roh[(int)$z['artikel_id']][(int)$z['lieferant_id']] = $z;
        }

        $erg = [];
        foreach ($positionen as $p) {
            $eigene = $roh[(int)$p['artikel_id']] ?? [];
            $vater  = $p['vaterartikel_id'] ? ($roh[(int)$p['vaterartikel_id']] ?? []) : [];
            foreach ($eigene + $vater as $liefId => $z) {   // eigene Zuordnung hat Vorrang
                if ((int)$z['ds'] === 1) {
                    $erg[(int)$p['artikel_id']][$liefId] = $z;
                }
            }
        }
        return $erg;
    }
}
