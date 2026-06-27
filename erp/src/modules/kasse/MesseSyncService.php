<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../lager/LagerService.php';

class MesseSyncService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Umbuchung zur Messe ───────────────────────────────────────────────────

    /**
     * Bucht gescannte Artikel vom Hauptlager ins Messe-Lager (K2) um.
     * Erstellt einen Sync-Datensatz als Pre-Sync-Basis.
     *
     * $positionen: [{artikel_id, bezeichnung, ean?, menge, charge?}]
     * Gibt sync_id zurück — wird für Pre-Sync benötigt.
     */
    public function umbuchungZurMesse(array $positionen, int $vonLagerId, int $nachLagerId, int $kasseId, int $benutzerId): array
    {
        if (empty($positionen)) {
            return ['erfolg' => false, 'fehler' => 'Keine Positionen übergeben.'];
        }

        $lagerSvc  = new LagerService();
        $syncToken = bin2hex(random_bytes(16));

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO kassen_messe_sync
                    (kasse_id, lager_id, typ, status, artikel_count, sync_token, benutzer_id)
                VALUES
                    (:kasse_id, :lager_id, 'pre', 'vorbereitet', :anzahl, :token, :uid)
            ");
            $stmt->execute([
                ':kasse_id' => $kasseId,
                ':lager_id' => $nachLagerId,
                ':anzahl'   => count($positionen),
                ':token'    => $syncToken,
                ':uid'      => $benutzerId,
            ]);
            $syncId = (int)$this->db->lastInsertId();

            $stmtUmb = $this->db->prepare("
                INSERT INTO kassen_messe_umbuchungen
                    (sync_id, artikel_id, bezeichnung, ean, menge_raus)
                VALUES
                    (:sync_id, :artikel_id, :bezeichnung, :ean, :menge)
            ");

            foreach ($positionen as $pos) {
                // Umlagerung: Ausgang aus Hauptlager
                $lagerSvc->warenausgang([
                    'artikel_id'  => (int)$pos['artikel_id'],
                    'lager_id'    => $vonLagerId,
                    'menge'       => (float)$pos['menge'],
                    'charge'      => $pos['charge'] ?? null,
                    'referenz'    => 'Messe-Umbuchung Sync #' . $syncId,
                    'benutzer_id' => $benutzerId,
                ]);
                // Eingang ins Messe-Lager
                $lagerSvc->wareneingang([
                    'artikel_id'  => (int)$pos['artikel_id'],
                    'lager_id'    => $nachLagerId,
                    'menge'       => (float)$pos['menge'],
                    'charge'      => $pos['charge'] ?? null,
                    'referenz'    => 'Messe-Umbuchung Sync #' . $syncId,
                    'benutzer_id' => $benutzerId,
                ]);

                $stmtUmb->execute([
                    ':sync_id'    => $syncId,
                    ':artikel_id' => (int)$pos['artikel_id'],
                    ':bezeichnung'=> $pos['bezeichnung'],
                    ':ean'        => $pos['ean'] ?? null,
                    ':menge'      => (float)$pos['menge'],
                ]);
            }

            Logger::log('messe.umbuchung', 'kassen_messe_sync', $syncId, [
                'von_lager'  => $vonLagerId,
                'nach_lager' => $nachLagerId,
                'artikel'    => count($positionen),
            ], $benutzerId);

            $this->db->commit();
            return ['erfolg' => true, 'sync_id' => $syncId, 'sync_token' => $syncToken];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => $e->getMessage()];
        }
    }

    // ── Pre-Sync: Daten für Offline-Kasse zusammenstellen ────────────────────

    /**
     * Gibt alle Daten zurück die die Messe-Kasse für den Offline-Betrieb braucht.
     * Wird als JSON an die Offline-Kasse geliefert, die daraus ihre SQLite befüllt.
     */
    public function preSyncExportieren(int $syncId, int $lagerId): array
    {
        $sync = $this->getSyncById($syncId);
        if (!$sync || $sync['typ'] !== 'pre') {
            return ['erfolg' => false, 'fehler' => 'Sync-Datensatz nicht gefunden.'];
        }

        // Artikel aus dem Messe-Lager
        $stmt = $this->db->prepare("
            SELECT
                a.id, a.artikelnummer, a.name AS bezeichnung,
                a.steuerklasse_id, sk.satz AS steuer_prozent,
                a.ueberverkauf_erlaubt, a.charge_pflicht,
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
                COALESCE(
                    (SELECT SUM(lb.bestand) FROM lagerbestand lb
                     WHERE lb.artikel_id = a.id AND lb.lager_id = :lager_id), 0
                ) AS bestand,
                (SELECT ac.code FROM artikel_codes ac
                 WHERE ac.artikel_id = a.id AND ac.typ = 'GTIN13' LIMIT 1) AS ean
            FROM kassen_messe_umbuchungen u
            INNER JOIN artikel a ON a.id = u.artikel_id
            LEFT JOIN steuerklassen sk ON sk.id = a.steuerklasse_id
            WHERE u.sync_id = :sync_id
        ");
        $stmt->execute([':sync_id' => $syncId, ':lager_id' => $lagerId]);
        $artikel = $stmt->fetchAll();

        // Gültige Gutscheine
        $gutscheine = $this->db->query("
            SELECT code, betrag_original, betrag_verbleibend, gueltig_bis
            FROM gutscheine
            WHERE aktiv = 1 AND betrag_verbleibend > 0
              AND (gueltig_bis IS NULL OR gueltig_bis >= CURDATE())
        ")->fetchAll();

        // Kassen-Konfiguration
        $kasse = $this->db->prepare("SELECT * FROM kassen WHERE id = :id");
        $kasse->execute([':id' => $sync['kasse_id']]);
        $kasseConfig = $kasse->fetch();

        return [
            'erfolg'     => true,
            'sync_id'    => $syncId,
            'sync_token' => $sync['sync_token'],
            'exportiert_am' => date('Y-m-d H:i:s'),
            'kasse'      => $kasseConfig,
            'lager_id'   => $lagerId,
            'artikel'    => $artikel,
            'gutscheine' => $gutscheine,
        ];
    }

    // ── Post-Sync: Offline-Bons zurückspielen ────────────────────────────────

    /**
     * Verarbeitet die von der Offline-Kasse hochgeladenen Bons.
     * $payload: Ausgabe von preSyncExportieren + offline erstellte Bons
     */
    public function postSyncVerarbeiten(array $payload, int $benutzerId): array
    {
        $syncToken = $payload['sync_token'] ?? '';
        $sync = $this->getSyncByToken($syncToken);
        if (!$sync) {
            return ['erfolg' => false, 'fehler' => 'Ungültiger Sync-Token.'];
        }
        if ($sync['status'] === 'abgeschlossen') {
            return ['erfolg' => false, 'fehler' => 'Post-Sync wurde bereits durchgeführt.'];
        }

        $bons         = $payload['bons']      ?? [];
        $lagerId      = (int)$sync['lager_id'];
        $kasseId      = (int)$sync['kasse_id'];
        $bonCount     = 0;
        $umsatz       = 0.0;
        $fehler       = [];

        $this->db->beginTransaction();
        try {
            foreach ($bons as $bonDaten) {
                // Bon anlegen (Offline-Bons haben bereits bon_nr vom Gerät)
                $stmt = $this->db->prepare("
                    INSERT INTO kassen_bons
                        (bon_nr, typ, kasse_id, kunden_id, zahlungsart, bruttobetrag,
                         gegeben, rueckgeld, bar_betrag, karten_betrag,
                         gutschein_code, gutschein_betrag,
                         rksv_signatur, rksv_qr,
                         benutzer_id, erstellt_am, notiz)
                    VALUES
                        (:bon_nr, 'verkauf', :kasse_id, :kunden_id, :zahlungsart, :bruttobetrag,
                         :gegeben, :rueckgeld, :bar_betrag, :karten_betrag,
                         :gutschein_code, :gutschein_betrag,
                         :rksv_signatur, :rksv_qr,
                         :benutzer_id, :erstellt_am, :notiz)
                ");
                $stmt->execute([
                    ':bon_nr'           => $bonDaten['bon_nr'],
                    ':kasse_id'         => $kasseId,
                    ':kunden_id'        => $bonDaten['kunden_id']        ?? null,
                    ':zahlungsart'      => $bonDaten['zahlungsart']      ?? 'bar',
                    ':bruttobetrag'     => $bonDaten['bruttobetrag']     ?? 0,
                    ':gegeben'          => $bonDaten['gegeben']          ?? null,
                    ':rueckgeld'        => $bonDaten['rueckgeld']        ?? null,
                    ':bar_betrag'       => $bonDaten['bar_betrag']       ?? null,
                    ':karten_betrag'    => $bonDaten['karten_betrag']    ?? null,
                    ':gutschein_code'   => $bonDaten['gutschein_code']   ?? null,
                    ':gutschein_betrag' => $bonDaten['gutschein_betrag'] ?? null,
                    ':rksv_signatur'    => $bonDaten['rksv_signatur']    ?? null,
                    ':rksv_qr'          => $bonDaten['rksv_qr']          ?? null,
                    ':benutzer_id'      => $benutzerId,
                    ':erstellt_am'      => $bonDaten['erstellt_am']      ?? date('Y-m-d H:i:s'),
                    ':notiz'            => 'Messe-Import Sync #' . $sync['id'],
                ]);
                $bonId = (int)$this->db->lastInsertId();

                // Positionen
                $stmtPos = $this->db->prepare("
                    INSERT INTO kassen_bon_positionen
                        (bon_id, block, artikel_id, bezeichnung, ean, menge,
                         einzelpreis_brutto, rabatt_prozent, steuer_prozent, charge, sort_order)
                    VALUES
                        (:bon_id, :block, :artikel_id, :bezeichnung, :ean, :menge,
                         :einzelpreis_brutto, :rabatt_prozent, :steuer_prozent, :charge, :sort)
                ");
                foreach (($bonDaten['positionen'] ?? []) as $i => $pos) {
                    $stmtPos->execute([
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
                }

                $bonCount++;
                $umsatz += (float)($bonDaten['bruttobetrag'] ?? 0);
            }

            // Sync abschließen
            $this->db->prepare("
                UPDATE kassen_messe_sync
                SET status = 'abgeschlossen', bon_count = :bons, umsatz = :umsatz,
                    abgeschlossen_am = NOW()
                WHERE id = :id
            ")->execute([':bons' => $bonCount, ':umsatz' => $umsatz, ':id' => $sync['id']]);

            Logger::log('messe.post_sync', 'kassen_messe_sync', (int)$sync['id'], [
                'bons'   => $bonCount,
                'umsatz' => $umsatz,
                'fehler' => count($fehler),
            ], $benutzerId);

            $this->db->commit();
            return [
                'erfolg'    => true,
                'bon_count' => $bonCount,
                'umsatz'    => $umsatz,
                'fehler'    => $fehler,
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => $e->getMessage()];
        }
    }

    // ── Rückkehr: Restbestand zurückbuchen ───────────────────────────────────

    /**
     * Verarbeitet die Rückkehr von der Messe.
     * $rueckgabe: [{artikel_id, menge_rueck}] — was zurückkommt.
     * Rest (menge_raus - menge_rueck) = verkauft + Schwund wird vom System berechnet.
     * $schwund: [{artikel_id, menge}] — separat erfasste Verluste.
     */
    public function rueckkehrVerarbeiten(int $syncId, array $rueckgabe, array $schwund, int $vonLagerId, int $nachLagerId, int $benutzerId): array
    {
        $sync = $this->getSyncById($syncId);
        if (!$sync) return ['erfolg' => false, 'fehler' => 'Sync nicht gefunden.'];

        $lagerSvc  = new LagerService();
        $rueckIdx  = [];
        foreach ($rueckgabe as $r) {
            $rueckIdx[(int)$r['artikel_id']] = (float)$r['menge_rueck'];
        }
        $schwundIdx = [];
        foreach ($schwund as $s) {
            $schwundIdx[(int)$s['artikel_id']] = (float)$s['menge'];
        }

        $this->db->beginTransaction();
        try {
            $umbuchungen = $this->getUmbuchungenBySyncId($syncId);
            foreach ($umbuchungen as $umb) {
                $artId      = (int)$umb['artikel_id'];
                $mengeRueck = $rueckIdx[$artId]  ?? 0.0;
                $mengeSchwund = $schwundIdx[$artId] ?? 0.0;

                // Rücklagerung: Messe-Lager → Hauptlager
                if ($mengeRueck > 0) {
                    $lagerSvc->warenausgang([
                        'artikel_id'  => $artId,
                        'lager_id'    => $vonLagerId,
                        'menge'       => $mengeRueck,
                        'referenz'    => 'Messe-Rückkehr Sync #' . $syncId,
                        'benutzer_id' => $benutzerId,
                    ]);
                    $lagerSvc->wareneingang([
                        'artikel_id'  => $artId,
                        'lager_id'    => $nachLagerId,
                        'menge'       => $mengeRueck,
                        'referenz'    => 'Messe-Rückkehr Sync #' . $syncId,
                        'benutzer_id' => $benutzerId,
                    ]);
                }

                // Schwund: Ausgang aus Messe-Lager ohne Gegenbuchung
                if ($mengeSchwund > 0) {
                    $lagerSvc->warenausgang([
                        'artikel_id'  => $artId,
                        'lager_id'    => $vonLagerId,
                        'menge'       => $mengeSchwund,
                        'referenz'    => 'Schwund Messe Sync #' . $syncId,
                        'benutzer_id' => $benutzerId,
                    ]);
                }

                // Umbuchungs-Zeile aktualisieren
                $this->db->prepare("
                    UPDATE kassen_messe_umbuchungen
                    SET menge_rueck = :rueck, menge_schwund = :schwund
                    WHERE id = :id
                ")->execute([':rueck' => $mengeRueck, ':schwund' => $mengeSchwund, ':id' => $umb['id']]);
            }

            Logger::log('messe.rueckkehr', 'kassen_messe_sync', $syncId, [
                'rueckgabe_positionen' => count($rueckgabe),
                'schwund_positionen'   => count($schwund),
            ], $benutzerId);

            $this->db->commit();
            return ['erfolg' => true];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => $e->getMessage()];
        }
    }

    // ── Hilfs-Abfragen ────────────────────────────────────────────────────────

    public function getSyncById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM kassen_messe_sync WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function getSyncByToken(string $token): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM kassen_messe_sync WHERE sync_token = :token");
        $stmt->execute([':token' => $token]);
        return $stmt->fetch() ?: null;
    }

    public function getOffeneSyncs(int $kasseId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, l.bezeichnung AS lager_name
            FROM kassen_messe_sync s
            LEFT JOIN lager l ON l.id = s.lager_id
            WHERE s.kasse_id = :kid AND s.status = 'vorbereitet'
            ORDER BY s.erstellt_am DESC
        ");
        $stmt->execute([':kid' => $kasseId]);
        return $stmt->fetchAll();
    }

    private function getUmbuchungenBySyncId(int $syncId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM kassen_messe_umbuchungen WHERE sync_id = :id
        ");
        $stmt->execute([':id' => $syncId]);
        return $stmt->fetchAll();
    }
}
