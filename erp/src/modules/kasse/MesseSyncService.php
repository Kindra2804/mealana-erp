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
     * Pro Artikel+Charge-Kombination eine eigene Position (nicht pro Artikel
     * zusammengefasst) — sonst kann bei der Rückkehr nicht mehr rekonstruiert
     * werden, welche Charge zurückkommt/verkauft/Schwund ist.
     * Gibt sync_id zurück — wird für Pre-Sync benötigt.
     */
    public function umbuchungZurMesse(array $positionen, int $vonLagerId, int $nachLagerId, int $kasseId, int $benutzerId): array
    {
        if (empty($positionen)) {
            return ['erfolg' => false, 'fehler' => 'Keine Positionen übergeben.'];
        }

        $lagerSvc = new LagerService();

        $this->db->beginTransaction();
        try {
            // Offene Vorbereitung für diese Kasse+Lager-Kombination wiederverwenden,
            // statt bei jedem "Umbuchung durchführen"-Klick ein neues Sync-Paket anzulegen.
            $stmtFind = $this->db->prepare("
                SELECT id, sync_token FROM kassen_messe_sync
                WHERE kasse_id = :kasse_id AND lager_id = :lager_id AND status = 'vorbereitet'
                ORDER BY id DESC LIMIT 1
            ");
            $stmtFind->execute([':kasse_id' => $kasseId, ':lager_id' => $nachLagerId]);
            $bestehend = $stmtFind->fetch();

            if ($bestehend) {
                $syncId    = (int)$bestehend['id'];
                $syncToken = $bestehend['sync_token'];
            } else {
                $syncToken = bin2hex(random_bytes(16));
                $stmt = $this->db->prepare("
                    INSERT INTO kassen_messe_sync
                        (kasse_id, lager_id, typ, status, artikel_count, sync_token, benutzer_id)
                    VALUES
                        (:kasse_id, :lager_id, 'pre', 'vorbereitet', 0, :token, :uid)
                ");
                $stmt->execute([
                    ':kasse_id' => $kasseId,
                    ':lager_id' => $nachLagerId,
                    ':token'    => $syncToken,
                    ':uid'      => $benutzerId,
                ]);
                $syncId = (int)$this->db->lastInsertId();
            }

            // Gleicher Artikel+Charge in diesem Sync schon vorhanden? -> Menge addieren statt Duplikat anlegen
            // (rueckkehrVerarbeiten() geht von genau einer Zeile pro Artikel+Charge pro Sync aus)
            $stmtFindUmb = $this->db->prepare("
                SELECT id FROM kassen_messe_umbuchungen
                WHERE sync_id = :sync_id AND artikel_id = :artikel_id AND charge <=> :charge
            ");
            $stmtUpdUmb = $this->db->prepare("
                UPDATE kassen_messe_umbuchungen SET menge_raus = menge_raus + :menge WHERE id = :id
            ");
            $stmtUmb = $this->db->prepare("
                INSERT INTO kassen_messe_umbuchungen
                    (sync_id, artikel_id, bezeichnung, ean, charge, menge_raus)
                VALUES
                    (:sync_id, :artikel_id, :bezeichnung, :ean, :charge, :menge)
            ");

            foreach ($positionen as $pos) {
                $charge = $pos['charge'] ?? null;

                // Umlagerung: Ausgang aus Hauptlager
                $lagerSvc->warenausgang([
                    'artikel_id'  => (int)$pos['artikel_id'],
                    'lager_id'    => $vonLagerId,
                    'menge'       => (float)$pos['menge'],
                    'charge'      => $charge,
                    'referenz'    => 'Messe-Umbuchung Sync #' . $syncId,
                    'benutzer_id' => $benutzerId,
                ]);
                // Eingang ins Messe-Lager
                $lagerSvc->wareneingang([
                    'artikel_id'  => (int)$pos['artikel_id'],
                    'lager_id'    => $nachLagerId,
                    'menge'       => (float)$pos['menge'],
                    'charge'      => $charge,
                    'referenz'    => 'Messe-Umbuchung Sync #' . $syncId,
                    'benutzer_id' => $benutzerId,
                ]);

                $stmtFindUmb->execute([
                    ':sync_id'    => $syncId,
                    ':artikel_id' => (int)$pos['artikel_id'],
                    ':charge'     => $charge,
                ]);
                $vorhanden = $stmtFindUmb->fetch();

                if ($vorhanden) {
                    $stmtUpdUmb->execute([':menge' => (float)$pos['menge'], ':id' => $vorhanden['id']]);
                } else {
                    $stmtUmb->execute([
                        ':sync_id'    => $syncId,
                        ':artikel_id' => (int)$pos['artikel_id'],
                        ':bezeichnung'=> $pos['bezeichnung'],
                        ':ean'        => $pos['ean'] ?? null,
                        ':charge'     => $charge,
                        ':menge'      => (float)$pos['menge'],
                    ]);
                }
            }

            $stmtCount = $this->db->prepare("SELECT COUNT(*) FROM kassen_messe_umbuchungen WHERE sync_id = :id");
            $stmtCount->execute([':id' => $syncId]);
            $this->db->prepare("UPDATE kassen_messe_sync SET artikel_count = :n WHERE id = :id")
                ->execute([':n' => (int)$stmtCount->fetchColumn(), ':id' => $syncId]);

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
     * Wird als JSON an die Offline-Kasse geliefert, die daraus ihre IndexedDB befüllt.
     * lagerId wird nicht mehr vom Aufrufer verlangt — steht schon im Sync-Datensatz
     * (vereinfacht den Client: der braucht nur noch die sync_id aus der URL).
     */
    public function preSyncExportieren(int $syncId): array
    {
        $sync = $this->getSyncById($syncId);
        if (!$sync || $sync['typ'] !== 'pre') {
            return ['erfolg' => false, 'fehler' => 'Sync-Datensatz nicht gefunden.'];
        }
        $lagerId = (int)$sync['lager_id'];

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

        // Chargen-Aufschlüsselung je Artikel — genau die Chargen (+ Mengen), die für
        // DIESEN Sync tatsächlich umgebucht wurden. Kritisch für charge_pflicht-Artikel:
        // der Offline-Client darf nur aus diesen echten Chargen verkaufen, keine Freitext-Eingabe.
        $stmtChargen = $this->db->prepare("
            SELECT artikel_id, charge, menge_raus
            FROM kassen_messe_umbuchungen
            WHERE sync_id = :sync_id AND charge IS NOT NULL
        ");
        $stmtChargen->execute([':sync_id' => $syncId]);
        $chargenProArtikel = [];
        foreach ($stmtChargen->fetchAll() as $c) {
            $chargenProArtikel[(int)$c['artikel_id']][] = [
                'charge' => $c['charge'],
                'menge'  => (float)$c['menge_raus'],
            ];
        }
        foreach ($artikel as &$a) {
            $a['chargen'] = $chargenProArtikel[(int)$a['id']] ?? [];
        }
        unset($a);

        // Gültige Gutscheine — Gutschein-Modul (Tabelle `gutscheine`) existiert noch nicht,
        // daher hier bewusst leer statt Fehler. Sobald das Modul gebaut ist, hier die
        // echte Abfrage ergänzen.
        $gutscheine = [];

        // Kassen-Konfiguration
        $stmtKasse = $this->db->prepare("SELECT * FROM kassen WHERE id = :id");
        $stmtKasse->execute([':id' => $sync['kasse_id']]);
        $kasseConfig = $stmtKasse->fetch();

        // Schnellwahl-Slots
        $stmtSw = $this->db->prepare("
            SELECT ksw.slot, ksw.artikel_id, ksw.label, a.name AS artikel_name
            FROM kassen_schnellwahl ksw
            LEFT JOIN artikel a ON a.id = ksw.artikel_id
            WHERE ksw.kasse_id = :id
            ORDER BY ksw.slot
        ");
        $stmtSw->execute([':id' => $sync['kasse_id']]);
        $schnellwahl = $stmtSw->fetchAll();

        // Startzähler für die Offline-Belegnummerierung: Anzahl bereits vorhandener
        // Bons dieser Kasse im laufenden Jahr (gleiche Logik wie KassenService::naechsteBonNr()),
        // damit der Offline-Client lückenlos weiterzählen kann statt Kollisionen zu riskieren.
        $stmtCount = $this->db->prepare("
            SELECT COUNT(*) FROM kassen_bons
            WHERE kasse_id = :kasse_id AND YEAR(erstellt_am) = :jahr
        ");
        $stmtCount->execute([':kasse_id' => $sync['kasse_id'], ':jahr' => date('Y')]);
        $bonNrStart = (int)$stmtCount->fetchColumn();

        // Divers-Platzhalter (99-9999) für "Freier Artikel" — gleicher Mechanismus wie online
        $diversArtikelId = (int)($this->db->query("SELECT id FROM artikel WHERE artikelnummer = '99-9999' LIMIT 1")->fetchColumn() ?: 0);

        return [
            'erfolg'         => true,
            'sync_id'        => $syncId,
            'sync_token'     => $sync['sync_token'],
            'exportiert_am'  => date('Y-m-d H:i:s'),
            'kasse'          => $kasseConfig,
            'lager_id'       => $lagerId,
            'artikel'        => $artikel,
            'gutscheine'     => $gutscheine,
            'schnellwahl'    => $schnellwahl,
            'bon_nr_jahr'    => (int)date('Y'),
            'bon_nr_zaehler' => $bonNrStart,
            'divers_artikel_id' => $diversArtikelId,
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
     * $rueckgabe: [{artikel_id, charge?, menge_rueck}] — was zurückkommt.
     * Rest (menge_raus - menge_rueck) = verkauft + Schwund wird vom System berechnet.
     * $schwund: [{artikel_id, charge?, menge}] — separat erfasste Verluste.
     * Wichtig: pro Charge einzeln buchen (nicht pro Artikel zusammengefasst),
     * sonst würde die Rückbuchung die Chargen-Zuordnung im Lagerbestand zerstören.
     */
    public function rueckkehrVerarbeiten(int $syncId, array $rueckgabe, array $schwund, int $vonLagerId, int $nachLagerId, int $benutzerId): array
    {
        $sync = $this->getSyncById($syncId);
        if (!$sync) return ['erfolg' => false, 'fehler' => 'Sync nicht gefunden.'];

        $lagerSvc  = new LagerService();
        $schluessel = fn($artId, $charge) => $artId . '|' . ($charge ?? '');

        $rueckIdx  = [];
        foreach ($rueckgabe as $r) {
            $rueckIdx[$schluessel((int)$r['artikel_id'], $r['charge'] ?? null)] = (float)$r['menge_rueck'];
        }
        $schwundIdx = [];
        foreach ($schwund as $s) {
            $schwundIdx[$schluessel((int)$s['artikel_id'], $s['charge'] ?? null)] = (float)$s['menge'];
        }

        $this->db->beginTransaction();
        try {
            $umbuchungen = $this->getUmbuchungenBySyncId($syncId);
            foreach ($umbuchungen as $umb) {
                $artId        = (int)$umb['artikel_id'];
                $charge       = $umb['charge'] ?? null;
                $key          = $schluessel($artId, $charge);
                $mengeRueck   = $rueckIdx[$key]   ?? 0.0;
                $mengeSchwund = $schwundIdx[$key] ?? 0.0;

                // Rücklagerung: Messe-Lager → Hauptlager (gleiche Charge!)
                if ($mengeRueck > 0) {
                    $lagerSvc->warenausgang([
                        'artikel_id'  => $artId,
                        'lager_id'    => $vonLagerId,
                        'menge'       => $mengeRueck,
                        'charge'      => $charge,
                        'referenz'    => 'Messe-Rückkehr Sync #' . $syncId,
                        'benutzer_id' => $benutzerId,
                    ]);
                    $lagerSvc->wareneingang([
                        'artikel_id'  => $artId,
                        'lager_id'    => $nachLagerId,
                        'menge'       => $mengeRueck,
                        'charge'      => $charge,
                        'referenz'    => 'Messe-Rückkehr Sync #' . $syncId,
                        'benutzer_id' => $benutzerId,
                    ]);
                }

                // Schwund: eigener Bewegungstyp 'schwund' für klare Filterbarkeit
                if ($mengeSchwund > 0) {
                    $lagerSvc->warenSchwund([
                        'artikel_id'  => $artId,
                        'lager_id'    => $vonLagerId,
                        'menge'       => $mengeSchwund,
                        'charge'      => $charge,
                        'referenz'    => 'Schwund Messe Sync #' . $syncId,
                        'benutzer_id' => $benutzerId,
                    ]);
                }

                // Verkaufte Menge aus Messe-Lager ausbuchen (gleiche Charge!)
                // (Offline-Kasse hat lokal gebucht; MariaDB-Stand war noch menge_raus)
                $mengeVerkauft = (float)$umb['menge_raus'] - $mengeRueck - $mengeSchwund;
                if ($mengeVerkauft > 0) {
                    $lagerSvc->warenausgang([
                        'artikel_id'  => $artId,
                        'lager_id'    => $vonLagerId,
                        'menge'       => $mengeVerkauft,
                        'charge'      => $charge,
                        'referenz'    => 'Messe-Verkäufe Sync #' . $syncId,
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

    /**
     * True, solange für diese Kasse Messe-Daten unterwegs sind, die noch nicht
     * per Post-Sync hochgeladen wurden (status='vorbereitet') — der eigentliche
     * Auslöser für die Bon-Nr-Kollisionsgefahr, siehe project_kassen_verwaltung
     * Notizen. Bewusst NICHT an kassen.modus geknüpft: der Admin-Schalter kann
     * manuell falsch stehen, dieser Status ist die tatsächliche Quelle der Wahrheit.
     */
    public function hatOffenenResync(int $kasseId): bool
    {
        return count($this->getOffeneSyncs($kasseId)) > 0;
    }

    public function getOffeneSyncs(int $kasseId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, l.name AS lager_name
            FROM kassen_messe_sync s
            LEFT JOIN lager l ON l.id = s.lager_id
            WHERE s.kasse_id = :kid AND s.status = 'vorbereitet'
            ORDER BY s.erstellt_am DESC
        ");
        $stmt->execute([':kid' => $kasseId]);
        return $stmt->fetchAll();
    }

    /** Post-gesynct (Bons hochgeladen), aber noch nicht zurückgebucht — für die "Von Messe zurück"-Seite. */
    public function getSyncsFuerRueckkehr(): array
    {
        $stmt = $this->db->query("
            SELECT s.*, l.name AS lager_name, k.name AS kasse_name
            FROM kassen_messe_sync s
            LEFT JOIN lager l ON l.id = s.lager_id
            LEFT JOIN kassen k ON k.id = s.kasse_id
            WHERE s.status = 'abgeschlossen'
            ORDER BY s.erstellt_am DESC
        ");
        return $stmt->fetchAll();
    }

    public function getUmbuchungenBySyncId(int $syncId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM kassen_messe_umbuchungen WHERE sync_id = :id
        ");
        $stmt->execute([':id' => $syncId]);
        return $stmt->fetchAll();
    }
}
