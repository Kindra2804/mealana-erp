<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * ShopSyncRepository – DB-Zugriff für den Artikel/Kategorien-Sync zu WooCommerce.
 *
 * `artikel_shops` ist die zentrale Zuweisungs-/Status-Tabelle (siehe Migration 142):
 * eine Zeile pro Artikel+Shop. "Fällig" heißt: der Chip ist aktiv UND entweder
 * noch nie synced (`sync_status='pending'`), zuletzt fehlgeschlagen, oder der
 * Artikel wurde seit dem letzten Sync geändert (`artikel.aktualisiert_am`).
 */
class ShopSyncRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Alle Artikel-IDs, die für diesen Shop im Kanal aktiv sind (Vater UND
     * Kind gleichermaßen, da beide eine eigene artikel_shops-Zeile haben,
     * siehe upsertZuweisung()) -- Grundlage für scripts/bilder_export_fuer_shop.php:
     * nur für DIESE Artikel müssen Bilder überhaupt per FTP übertragen werden,
     * statt blind den kompletten uploads/artikel/-Ordner zu kopieren.
     */
    public function findArtikelIdsFuerShop(int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT artikel_id FROM artikel_shops WHERE shop_id = :shop_id AND aktiv = 1
        ");
        $stmt->execute(['shop_id' => $shopId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Alle Shops mit konfigurierter WooCommerce-Anbindung. */
    public function findAktiveShops(): array
    {
        $stmt = $this->db->query("
            SELECT id, slug, name, wc_url, wc_key, wc_secret, wp_username, wp_app_password,
                   bestellungen_letzter_sync, bulk_import_aktiv, sync_pausiert, bilder_basis_url,
                   aktueller_sync_log
            FROM shops
            WHERE ist_aktiv = 1 AND wc_url IS NOT NULL AND wc_key IS NOT NULL AND wc_secret IS NOT NULL
        ");
        return $stmt->fetchAll();
    }

    /**
     * Merkt sich die zuletzt verwendete Bilder-Basis-URL für den FTP-Bilder-
     * Workflow (siehe scripts/erstbefuellung_bilder.php) -- wird beim nächsten
     * Start in der "Shop-Synchronisierung"-Seite automatisch vorausgefüllt,
     * damit sie nicht jedes Mal neu eingetippt werden muss.
     */
    public function setBilderBasisUrl(int $shopId, string $url): void
    {
        $this->db->prepare("UPDATE shops SET bilder_basis_url = :url WHERE id = :id")
            ->execute(['url' => $url, 'id' => $shopId]);
    }

    /**
     * Sperre analog zum JTL-Komplettabgleich: solange ein manueller Bulk-Import
     * (scripts/erstbefuellung_bilder.php) für einen Shop läuft, überspringt der
     * laufende Cron (cron/shop_sync.php) diesen Shop komplett -- sonst Race
     * Condition zwischen beiden (doppelter Bild-Upload, veralteter Bilderstand).
     */
    public function setBulkImportAktiv(int $shopId, bool $aktiv): void
    {
        $this->db->prepare("UPDATE shops SET bulk_import_aktiv = :aktiv WHERE id = :id")
            ->execute(['aktiv' => $aktiv ? 1 : 0, 'id' => $shopId]);
    }

    /**
     * Merkt sich den Dateinamen des laufenden Komplettabgleich-/Bilder-FTP-Logs
     * in der DB (Migration 161) -- der Browser-Tab, der den Lauf gestartet hat,
     * kennt den Namen zwar auch selbst (siehe shop_sync_start.php), aber bei
     * Seiten-Reload/Tab-Wechsel während des Laufs geht dieses Wissen verloren.
     * shop_sync_status.php greift dann auf diese Spalte zurück, statt für immer
     * ohne Log-Text dazustehen (der "läuft"-Status kam über bulk_import_aktiv
     * ohnehin schon zuverlässig durch).
     */
    public function setAktuellerSyncLog(int $shopId, ?string $logName): void
    {
        $this->db->prepare("UPDATE shops SET aktueller_sync_log = :log WHERE id = :id")
            ->execute(['log' => $logName, 'id' => $shopId]);
    }

    /**
     * Pause-Schalter für die "Shop-Synchronisierung"-Seite (Migration 157) --
     * anders als bulk_import_aktiv wird das NUR vom Menschen über die UI gesetzt
     * und bleibt bestehen, bis er es wieder aufhebt (kein Selbst-Reset durch ein
     * Skript). cron/shop_sync.php prüft das pro Shop beim Start.
     */
    public function setSyncPausiert(int $shopId, bool $pausiert): void
    {
        $this->db->prepare("UPDATE shops SET sync_pausiert = :pausiert WHERE id = :id")
            ->execute(['pausiert' => $pausiert ? 1 : 0, 'id' => $shopId]);
    }

    /**
     * Fällige Artikel für einen Shop -- Standalone/Vater UND Kind-Artikel.
     * `vater_external_id` ist bei Standalone/Vater immer NULL, bei Kindern die
     * WooCommerce-Produkt-ID des Vaters (falls der schon synced ist -- wenn
     * nicht, überspringt ShopSyncService die Kind-Zeile für diesen Durchlauf).
     * ORDER BY stellt sicher, dass ein Vater IMMER vor seinen eigenen Kindern
     * kommt (Gruppierung nach vaterartikel_id/eigener id, dann Kind nach Vater).
     */
    public function findFaelligeArtikel(int $shopId, int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT ash.id AS artikel_shop_id, ash.external_id, a.id AS artikel_id,
                   a.vaterartikel_id, a.artikelnummer, a.name, a.kurzbeschreibung,
                   a.beschreibung, a.aktiv, a.hersteller_id, ash_vater.external_id AS vater_external_id
            FROM artikel_shops ash
            JOIN artikel a ON a.id = ash.artikel_id
            LEFT JOIN artikel_shops ash_vater
                   ON ash_vater.shop_id = ash.shop_id AND ash_vater.artikel_id = a.vaterartikel_id
            WHERE ash.shop_id = :shop_id
              AND ash.aktiv = 1
              -- Ein Kind kann nie sync'en, solange sein Vater im selben Shop
              -- nicht (mindestens) selbst eine aktive Zeile hat (siehe
              -- ShopSyncService::syncShop(), skippt ohne vater_external_id).
              -- Ohne diesen Filter würden genau solche für immer blockierten
              -- Kinder das LIMIT-Fenster auffressen, ohne je Fortschritt zu
              -- machen -- Ursache des Bugs vom 06.08.2026 ('meldet nichts zu
              -- tun, obwohl tausende Artikel offen sind', 55 Väter ohne eigene
              -- Zeile blockierten 679 Kinder unsichtbar). Steht ein Vater
              -- absichtlich auf inaktiv, bleibt das Kind hier ebenfalls
              -- ausgeschlossen -- das ist gewolltes Verhalten (siehe
              -- findKanalStatusFuerArtikel()-Kommentar), keine Regression.
              AND (a.vaterartikel_id IS NULL OR COALESCE(ash_vater.aktiv, 0) = 1)
              AND (
                  ash.sync_status IN ('pending', 'error')
                  OR a.aktualisiert_am > ash.synced_at
                  -- Artikel selbst ist längst synced, aber eines seiner Bilder
                  -- noch nicht (z.B. nachträglich hochgeladen) -- artikel_bilder_shops
                  -- hat einen eigenen Status, unabhängig von artikel_shops.
                  OR EXISTS (
                      SELECT 1 FROM artikel_bilder ab
                      LEFT JOIN artikel_bilder_shops abs
                             ON abs.bild_id = ab.id AND abs.shop_id = ash.shop_id
                      WHERE ab.artikel_id = a.id
                        AND (abs.id IS NULL OR abs.sync_status IN ('pending', 'error'))
                  )
                  -- Gleiches Muster wie bei Bildern: eine Lagerbuchung/Reservierung
                  -- ändert `lagerbestand.geaendert_am`/`reservierungen.geaendert_am`,
                  -- NICHT `artikel.aktualisiert_am` -- ohne diese Prüfung würde ein
                  -- längst synced Artikel bei reiner Bestandsänderung nie nachziehen.
                  -- Nur Bestand aus Lagern zählt, die auch tatsächlich in den Shop
                  -- einfließen (eigen, nicht Messe -- siehe findBestandInfo()).
                  OR EXISTS (
                      SELECT 1 FROM lagerbestand lb
                      JOIN lager l ON l.id = lb.lager_id AND l.lager_beziehung = 'eigen' AND l.typ != 'messe'
                      WHERE lb.artikel_id = a.id AND lb.geaendert_am > ash.synced_at
                  )
                  OR EXISTS (
                      SELECT 1 FROM reservierungen r
                      WHERE r.artikel_id = a.id AND r.geaendert_am > ash.synced_at
                  )
              )
            ORDER BY COALESCE(a.vaterartikel_id, a.id), (a.vaterartikel_id IS NOT NULL)
            LIMIT :limit
        ");
        $stmt->bindValue('shop_id', $shopId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Vorschau-Zahlen für den Komplettabgleich (Frage "wie lange dauert das
     * ungefähr" VOR dem Start, siehe scripts/komplettabgleich.php) -- gleiche
     * Fälligkeits-Bedingung wie findFaelligeArtikel(), nur gezählt statt
     * geholt, plus eine eigene Zahl für Kinder, deren Vater im Kanal nicht
     * aktiv ist (können nicht sync'en, sollen aber sichtbar bleiben statt
     * wieder stillschweigend zu verschwinden -- siehe findFaelligeArtikel()).
     */
    public function zaehleFaellige(int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                SUM(CASE WHEN a.vaterartikel_id IS NULL THEN 1 ELSE 0 END) AS vaeter,
                SUM(CASE WHEN a.vaterartikel_id IS NOT NULL AND COALESCE(ash_vater.aktiv, 0) = 1 THEN 1 ELSE 0 END) AS kinder,
                SUM(CASE WHEN a.vaterartikel_id IS NOT NULL AND COALESCE(ash_vater.aktiv, 0) = 0 THEN 1 ELSE 0 END) AS blockierte_kinder
            FROM artikel_shops ash
            JOIN artikel a ON a.id = ash.artikel_id
            LEFT JOIN artikel_shops ash_vater
                   ON ash_vater.shop_id = ash.shop_id AND ash_vater.artikel_id = a.vaterartikel_id
            WHERE ash.shop_id = :shop_id
              AND ash.aktiv = 1
              AND (
                  ash.sync_status IN ('pending', 'error')
                  OR a.aktualisiert_am > ash.synced_at
                  OR EXISTS (
                      SELECT 1 FROM artikel_bilder ab
                      LEFT JOIN artikel_bilder_shops abs
                             ON abs.bild_id = ab.id AND abs.shop_id = ash.shop_id
                      WHERE ab.artikel_id = a.id
                        AND (abs.id IS NULL OR abs.sync_status IN ('pending', 'error'))
                  )
                  OR EXISTS (
                      SELECT 1 FROM lagerbestand lb
                      JOIN lager l ON l.id = lb.lager_id AND l.lager_beziehung = 'eigen' AND l.typ != 'messe'
                      WHERE lb.artikel_id = a.id AND lb.geaendert_am > ash.synced_at
                  )
                  OR EXISTS (
                      SELECT 1 FROM reservierungen r
                      WHERE r.artikel_id = a.id AND r.geaendert_am > ash.synced_at
                  )
              )
        ");
        $stmt->bindValue('shop_id', $shopId, PDO::PARAM_INT);
        $stmt->execute();
        $zeilen = $stmt->fetch();

        $bilderStmt = $this->db->prepare("
            SELECT COUNT(*) FROM artikel_bilder ab
            JOIN artikel_shops ash ON ash.artikel_id = ab.artikel_id AND ash.shop_id = :shop_id AND ash.aktiv = 1
            LEFT JOIN artikel_bilder_shops abs ON abs.bild_id = ab.id AND abs.shop_id = :shop_id2
            WHERE abs.id IS NULL OR abs.sync_status IN ('pending', 'error')
        ");
        $bilderStmt->bindValue('shop_id', $shopId, PDO::PARAM_INT);
        $bilderStmt->bindValue('shop_id2', $shopId, PDO::PARAM_INT);
        $bilderStmt->execute();

        $abzumeldenStmt = $this->db->prepare("
            SELECT COUNT(*) FROM artikel_shops
            WHERE shop_id = :shop_id AND aktiv = 0 AND external_id IS NOT NULL AND sync_status = 'pending'
        ");
        $abzumeldenStmt->bindValue('shop_id', $shopId, PDO::PARAM_INT);
        $abzumeldenStmt->execute();

        return [
            'vaeter'             => (int)($zeilen['vaeter'] ?? 0),
            'kinder'             => (int)($zeilen['kinder'] ?? 0),
            'blockierte_kinder'  => (int)($zeilen['blockierte_kinder'] ?? 0),
            'bilder'             => (int)$bilderStmt->fetchColumn(),
            'abzumelden'         => (int)$abzumeldenStmt->fetchColumn(),
        ];
    }

    /**
     * Für die Erstbefüllungs-Bulk-Bildverknüpfung (siehe ShopSyncService::
     * erstbefuellungBilderPerUrl()): nur Artikel, die in WooCommerce schon
     * existieren (external_id gesetzt) UND mindestens ein noch nicht synctes
     * Bild haben. Echte ID-Cursor-Pagination (WHERE a.id > :letzte_id, ORDER
     * BY a.id) statt LIMIT/OFFSET -- garantiert Fortschritt auch wenn einzelne
     * Artikel dauerhaft überspringen/fehlschlagen (kein "Stecken bleiben" wie
     * bei findFaelligeArtikel(), das für den normalen Cron mit kleinem Limit
     * gedacht ist, nicht für einen einmaligen Gesamtdurchlauf).
     */
    public function findArtikelMitOffenenBildernUndExternalId(int $shopId, int $letzteArtikelId, int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT a.id AS artikel_id, ash.external_id, a.vaterartikel_id,
                   ash_vater.external_id AS vater_external_id
            FROM artikel a
            JOIN artikel_shops ash
                 ON ash.artikel_id = a.id AND ash.shop_id = :shop_id AND ash.aktiv = 1
            LEFT JOIN artikel_shops ash_vater
                   ON ash_vater.shop_id = ash.shop_id AND ash_vater.artikel_id = a.vaterartikel_id
            JOIN artikel_bilder ab ON ab.artikel_id = a.id
            LEFT JOIN artikel_bilder_shops abs
                   ON abs.bild_id = ab.id AND abs.shop_id = :shop_id2
            WHERE ash.external_id IS NOT NULL
              AND a.id > :letzte_id
              AND (abs.id IS NULL OR abs.sync_status IN ('pending', 'error'))
            ORDER BY a.id
            LIMIT :limit
        ");
        $stmt->bindValue('shop_id', $shopId, PDO::PARAM_INT);
        $stmt->bindValue('shop_id2', $shopId, PDO::PARAM_INT);
        $stmt->bindValue('letzte_id', $letzteArtikelId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Achsen eines Vater-Artikels, die als WooCommerce-Variations-Attribut taugen.
     * freitext/pflichtfreitext bewusst ausgeschlossen -- WooCommerce-Variationen
     * brauchen feste, abzählbare Werte, keinen offenen Text (siehe Plan/Memory).
     */
    public function findAchsenFuerArtikel(int $vaterId): array
    {
        $stmt = $this->db->prepare("
            SELECT va.id AS achse_id, va.name, va.code, va.abhaengig_von_achse_id
            FROM artikel_achsen aa
            JOIN varianten_achsen va ON va.id = aa.achse_id
            WHERE aa.artikel_id = :vater_id
              AND va.darstellungsform IN ('swatches', 'dropdown', 'radiobutton')
            ORDER BY aa.sort_order
        ");
        $stmt->execute(['vater_id' => $vaterId]);
        return $stmt->fetchAll();
    }

    /**
     * ALLE Achsen-Werte eines Vater-Artikels, nicht nach einzelner Achse gefiltert --
     * Grundlage für VariantenService::baueAchsenDimensionen() (Sub-Achsen-Union,
     * siehe syncAchsenFuerVater() in ShopSyncService).
     */
    public function findAlleWerteFuerVater(int $vaterId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, achse_id, wert, aktualisiert_am
            FROM varianten_achse_werte
            WHERE artikel_id = :vater_id
            ORDER BY achse_id, sort_order, wert
        ");
        $stmt->execute(['vater_id' => $vaterId]);
        return $stmt->fetchAll();
    }

    /** Werte einer Achse am Vater-Artikel (= die "options" für das WC-Attribut). */
    public function findWerteFuerAchse(int $vaterId, int $achseId): array
    {
        $stmt = $this->db->prepare("
            SELECT id AS wert_id, wert
            FROM varianten_achse_werte
            WHERE artikel_id = :vater_id AND achse_id = :achse_id
            ORDER BY sort_order
        ");
        $stmt->execute(['vater_id' => $vaterId, 'achse_id' => $achseId]);
        return $stmt->fetchAll();
    }

    /** Bestehende Shop-Zuordnung einer Achse (Idempotenz, wie kategorie_shops). */
    public function findAchseShopZuweisung(int $achseId, int $shopId): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM varianten_achsen_shops WHERE achse_id = :achse_id AND shop_id = :shop_id");
        $stmt->execute(['achse_id' => $achseId, 'shop_id' => $shopId]);
        return $stmt->fetch() ?: false;
    }

    public function upsertAchseZuweisung(int $achseId, int $shopId, string $externeAttributId): void
    {
        $this->db->prepare("
            INSERT INTO varianten_achsen_shops (achse_id, shop_id, externe_attribut_id)
            VALUES (:achse_id, :shop_id, :externe_attribut_id)
            ON DUPLICATE KEY UPDATE externe_attribut_id = VALUES(externe_attribut_id)
        ")->execute(['achse_id' => $achseId, 'shop_id' => $shopId, 'externe_attribut_id' => $externeAttributId]);
    }

    /** Bestehende Shop-Zuordnung eines Achsenwerts (Idempotenz, wie kategorie_shops). */
    public function findWertShopZuweisung(int $wertId, int $shopId): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM varianten_achse_werte_shops WHERE wert_id = :wert_id AND shop_id = :shop_id");
        $stmt->execute(['wert_id' => $wertId, 'shop_id' => $shopId]);
        return $stmt->fetch() ?: false;
    }

    /**
     * Vater-Artikel, deren Achsenwerte für diesen Shop bereits als Terms angelegt
     * sind, sich seit dem letzten Sync aber geändert haben (Change-Detection
     * unabhängig von Artikel-Fälligkeit -- gleiches Muster wie
     * findFaelligeKategorien()/findFaelligeHersteller()). Eine reine
     * Wert-Umbenennung ohne gerade fälligen Kind-Artikel würde sonst nie
     * nachgezogen -- genau das ist am 2026-07-31 real passiert: der komplette
     * Artikel-Rückstau war schon durchsynct, bevor die Wert-Umbenennung selbst
     * scharf geschaltet wurde, danach griff syncAchsenFuerVater() (nur im
     * Artikel-Fälligkeits-Loop aufgerufen) nie wieder.
     *
     * @return int[] vaterartikel_id
     */
    public function findFaelligeVaterFuerAchsenwerte(int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT v.artikel_id
            FROM varianten_achse_werte v
            JOIN varianten_achse_werte_shops vws ON vws.wert_id = v.id AND vws.shop_id = :shop_id
            WHERE vws.externe_term_id IS NOT NULL
              AND (vws.synced_at IS NULL OR v.aktualisiert_am > vws.synced_at)
        ");
        $stmt->execute(['shop_id' => $shopId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function upsertWertZuweisung(int $wertId, int $shopId, string $externeTermId): void
    {
        $this->db->prepare("
            INSERT INTO varianten_achse_werte_shops (wert_id, shop_id, externe_term_id, synced_at)
            VALUES (:wert_id, :shop_id, :externe_term_id, NOW())
            ON DUPLICATE KEY UPDATE externe_term_id = VALUES(externe_term_id), synced_at = NOW()
        ")->execute(['wert_id' => $wertId, 'shop_id' => $shopId, 'externe_term_id' => $externeTermId]);
    }

    /** Welche Achse+Wert-Kombination genau dieses Kind hat (für den Variation-Payload). */
    public function findKombinationFuerKind(int $kindArtikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT vaw.achse_id, vaw.id AS wert_id, vaw.wert
            FROM varianten_kombination_werte vkw
            JOIN varianten_achse_werte vaw ON vaw.id = vkw.wert_id
            WHERE vkw.kombination_id = :kind_id
        ");
        $stmt->execute(['kind_id' => $kindArtikelId]);
        return $stmt->fetchAll();
    }

    /** Name des Herstellers (für den WC-Attribut-Term). */
    public function findHerstellerName(int $herstellerId): ?string
    {
        $stmt = $this->db->prepare("SELECT name FROM hersteller WHERE id = :id");
        $stmt->execute(['id' => $herstellerId]);
        $name = $stmt->fetchColumn();
        return $name !== false ? $name : null;
    }

    /**
     * Vollständige Herstellerdaten für die GPSR-Kontaktbeschreibung (Art. 19
     * EU GPSR) -- liefert nur die ausgeschriebenen Ländernamen für den
     * Anzeigetext (per LEFT JOIN). Die EU-Mitgliedschaft selbst wird bewusst
     * NICHT hier berechnet, sondern über HerstellerService::istEuLand()
     * abgefragt (schon bestehende, einzige Quelle für diese Prüfung -- siehe
     * ShopSyncService::baueHerstellerBeschreibung()).
     */
    public function findHerstellerDetails(int $herstellerId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT h.*, l1.name_de AS land_name, l2.name_de AS reo_land_name
            FROM hersteller h
            LEFT JOIN laender l1 ON l1.iso_code = h.land
            LEFT JOIN laender l2 ON l2.iso_code = h.reo_land
            WHERE h.id = :id
        ");
        $stmt->execute(['id' => $herstellerId]);
        return $stmt->fetch();
    }

    /**
     * Hersteller, die für diesen Shop schon angelegt sind, sich seit dem
     * letzten Sync aber geändert haben -- unabhängig von Artikel-Fälligkeit,
     * gleiches Muster wie findFaelligeKategorien() (siehe dort für die
     * Begründung, warum das eine eigenständige Prüfung braucht).
     *
     * @return int[] hersteller_id
     */
    public function findFaelligeHersteller(int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT hs.hersteller_id
            FROM hersteller_shops hs
            JOIN hersteller h ON h.id = hs.hersteller_id
            WHERE hs.shop_id = :shop_id
              AND hs.externe_term_id IS NOT NULL
              AND (hs.synced_at IS NULL OR h.aktualisiert_am > hs.synced_at)
        ");
        $stmt->execute(['shop_id' => $shopId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Bestehende Shop-Zuordnung eines Herstellers (Idempotenz, wie kategorie_shops). */
    public function findHerstellerShopZuweisung(int $herstellerId, int $shopId): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM hersteller_shops WHERE hersteller_id = :hersteller_id AND shop_id = :shop_id");
        $stmt->execute(['hersteller_id' => $herstellerId, 'shop_id' => $shopId]);
        return $stmt->fetch() ?: false;
    }

    /**
     * Die "Hersteller"-Attribut-ID ist für einen Shop immer dieselbe (ein
     * einziges globales Attribut) -- irgendeine schon befüllte Zeile dieses
     * Shops reicht, um sie wiederzuverwenden statt erneut per WC-API nachzusehen.
     */
    public function findHerstellerAttributIdFuerShop(int $shopId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT externe_attribut_id FROM hersteller_shops
            WHERE shop_id = :shop_id AND externe_attribut_id IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute(['shop_id' => $shopId]);
        $id = $stmt->fetchColumn();
        return $id !== false ? $id : null;
    }

    public function upsertHerstellerZuweisung(int $herstellerId, int $shopId, string $externeAttributId, string $externeTermId, ?string $externeManufacturerId = null): void
    {
        $this->db->prepare("
            INSERT INTO hersteller_shops (hersteller_id, shop_id, externe_attribut_id, externe_term_id, externe_manufacturer_id, synced_at)
            VALUES (:hersteller_id, :shop_id, :externe_attribut_id, :externe_term_id, :externe_manufacturer_id, NOW())
            ON DUPLICATE KEY UPDATE externe_attribut_id = VALUES(externe_attribut_id), externe_term_id = VALUES(externe_term_id), externe_manufacturer_id = VALUES(externe_manufacturer_id), synced_at = NOW()
        ")->execute([
            'hersteller_id' => $herstellerId,
            'shop_id' => $shopId,
            'externe_attribut_id' => $externeAttributId,
            'externe_term_id' => $externeTermId,
            'externe_manufacturer_id' => $externeManufacturerId,
        ]);
    }

    /** Bestehende Shop-Zuordnung eines Bildes (Idempotenz, artikel_bilder_shops existierte schon vor diesem Feature). */
    public function findBildShopZuweisung(int $bildId, int $shopId): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM artikel_bilder_shops WHERE bild_id = :bild_id AND shop_id = :shop_id");
        $stmt->execute(['bild_id' => $bildId, 'shop_id' => $shopId]);
        return $stmt->fetch() ?: false;
    }

    public function markiereBildSynced(int $bildId, int $shopId, string $externalId): void
    {
        $this->db->prepare("
            INSERT INTO artikel_bilder_shops (bild_id, shop_id, external_id, sync_status, synced_at)
            VALUES (:bild_id, :shop_id, :external_id, 'synced', NOW())
            ON DUPLICATE KEY UPDATE external_id = VALUES(external_id), sync_status = 'synced', synced_at = NOW(), fehler_meldung = NULL
        ")->execute(['bild_id' => $bildId, 'shop_id' => $shopId, 'external_id' => $externalId]);
    }

    public function markiereBildFehler(int $bildId, int $shopId, string $fehlermeldung): void
    {
        $this->db->prepare("
            INSERT INTO artikel_bilder_shops (bild_id, shop_id, sync_status, fehler_meldung)
            VALUES (:bild_id, :shop_id, 'error', :fehler)
            ON DUPLICATE KEY UPDATE sync_status = 'error', fehler_meldung = VALUES(fehler_meldung)
        ")->execute(['bild_id' => $bildId, 'shop_id' => $shopId, 'fehler' => $fehlermeldung]);
    }

    /**
     * Bestandsdaten für den Shop-Sync: `gesamtbestand` zählt NUR eigene,
     * nicht-Messe-Lager (`lager_beziehung='eigen' AND typ != 'messe'`,
     * Jackys Entscheidung 2026-07-21 -- Messe-Bestand ist laut
     * project_lager_konzept.md ohnehin für keinen Verkaufskanal außer der
     * Messekasse selbst verfügbar, Partner-/Händler-Außenlager sind nicht
     * ohne Weiteres aus dem Shop heraus versandfähig). `reserviert` ist
     * dasselbe Muster wie überall sonst im Code (offene Reservierungen,
     * unabhängig vom Lager). `hat_lagerstand=false` (z.B. Download-Artikel)
     * bedeutet: kein Bestandsfeld im Payload, Artikel bleibt immer kaufbar.
     */
    public function findBestandInfo(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                at.hat_lagerstand,
                a.ueberverkauf_erlaubt,
                COALESCE(SUM(CASE WHEN l.id IS NOT NULL THEN lb.bestand ELSE 0 END), 0) AS gesamtbestand,
                (SELECT COALESCE(SUM(r.menge), 0) FROM reservierungen r WHERE r.artikel_id = a.id AND r.status = 'offen') AS reserviert
            FROM artikel a
            JOIN artikel_typen at ON at.id = a.artikeltyp_id
            LEFT JOIN lagerbestand lb ON lb.artikel_id = a.id
            LEFT JOIN lager l ON l.id = lb.lager_id AND l.lager_beziehung = 'eigen' AND l.typ != 'messe'
            WHERE a.id = :artikel_id
            GROUP BY a.id, at.hat_lagerstand, a.ueberverkauf_erlaubt
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetch() ?: ['hat_lagerstand' => 0, 'ueberverkauf_erlaubt' => 0, 'gesamtbestand' => 0, 'reserviert' => 0];
    }

    /** Endkunden-Bruttopreis (kundengruppen.ist_standard=1), da Shops immer B2C sind. */
    public function findEndkundenPreis(int $artikelId): ?float
    {
        $stmt = $this->db->prepare("
            SELECT ap.brutto_vk
            FROM artikel_preise ap
            JOIN kundengruppen kg ON kg.id = ap.kundengruppen_id
            WHERE ap.artikel_id = :artikel_id AND kg.ist_standard = 1
              AND (ap.gueltig_ab IS NULL OR ap.gueltig_ab <= NOW())
              AND (ap.gueltig_bis IS NULL OR ap.gueltig_bis >= NOW())
            ORDER BY ap.gueltig_ab DESC
            LIMIT 1
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        $wert = $stmt->fetchColumn();
        return $wert !== false ? (float)$wert : null;
    }

    /**
     * Rohdaten für die Grundpreis-Berechnung (siehe ShopSyncService::baueGrundpreisFelder()) --
     * nicht Teil von findFaelligeArtikel(), weil dort nur die für JEDEN Artikel nötigen
     * Kernfelder stehen (gleiches Muster wie findEndkundenPreis()/findBestandInfo()).
     */
    public function findGrundpreisFelder(int $artikelId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT inhalt_menge, inhalt_einheit, grundpreis_bezugsmenge, grundpreis_anzeigen
            FROM artikel
            WHERE id = :id
        ");
        $stmt->execute(['id' => $artikelId]);
        return $stmt->fetch();
    }

    /**
     * Externe WooCommerce-Kategorie-IDs des Artikels für diesen Shop. Kategorien, die für DIESEN
     * Shop ausgeschlossen sind (kategorie_shops.ausgeschlossen) -- egal ob die Blatt-Kategorie
     * selbst oder eine ihrer Oberkategorien ("Wurzel gesperrt") -- werden bewusst nicht mitgeliefert.
     * Die Pfad-Prüfung (istKategoriePfadAusgeschlossen) kann nicht in der SQL-JOIN-Bedingung
     * passieren (Vorfahren-Kette, kein rekursives SQL), daher zweistufig: erst alle Blatt-
     * Kategorien des Artikels mit ihrer externen ID laden, dann pro Kategorie den Pfad prüfen.
     * Zusätzlich: artikel_kategorie_shop_ausschluss -- Artikel-eigene Ausnahme, wenn DIESE
     * Kategorie-Zuweisung für DIESEN einen Artikel in DIESEM Shop ausgeblendet sein soll, obwohl
     * Artikel UND Kategorie für den Shop grundsätzlich aktiv sind (z.B. Artikel steht in
     * Hersteller/Elisa UND Hersteller/Sonstige, soll in Kanal 1 aber nur unter Elisa erscheinen).
     */
    public function findWcKategorieIds(int $artikelId, int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT ak.kategorie_id, ks.externe_kategorie_id
            FROM artikel_kategorien ak
            JOIN kategorie_shops ks ON ks.kategorie_id = ak.kategorie_id AND ks.shop_id = :shop_id
            WHERE ak.artikel_id = :artikel_id AND ks.externe_kategorie_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM artikel_kategorie_shop_ausschluss aksa
                  WHERE aksa.artikel_id = ak.artikel_id AND aksa.kategorie_id = ak.kategorie_id AND aksa.shop_id = :shop_id2
              )
        ");
        $stmt->execute(['artikel_id' => $artikelId, 'shop_id' => $shopId, 'shop_id2' => $shopId]);

        $ergebnis = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!$this->istKategoriePfadAusgeschlossen((int)$row['kategorie_id'], $shopId)) {
                $ergebnis[] = $row['externe_kategorie_id'];
            }
        }
        return $ergebnis;
    }

    /** Artikel-eigene Kategorie/Shop-Ausnahmen als Set "kategorieId_shopId" (fürs UI im Artikel-Formular). */
    public function findKategorieShopAusschluesseFuerArtikel(int $artikelId): array
    {
        $stmt = $this->db->prepare("SELECT kategorie_id, shop_id FROM artikel_kategorie_shop_ausschluss WHERE artikel_id = :artikel_id");
        $stmt->execute(['artikel_id' => $artikelId]);
        $ergebnis = [];
        foreach ($stmt->fetchAll() as $row) {
            $ergebnis[$row['kategorie_id'] . '_' . $row['shop_id']] = true;
        }
        return $ergebnis;
    }

    /**
     * Blendet eine einzelne Kategorie-Zuweisung dieses Artikels für einen bestimmten Shop aus
     * (oder hebt die Ausnahme wieder auf). Markiert den Artikel als fällig, damit der Kategorie-
     * Payload beim nächsten Sync-Lauf tatsächlich aktualisiert wird (gleiches Muster wie
     * setKategorieAusschluss()).
     */
    public function setKategorieShopAusschlussFuerArtikel(int $artikelId, int $kategorieId, int $shopId, bool $ausgeschlossen): void
    {
        if ($ausgeschlossen) {
            $this->db->prepare("
                INSERT IGNORE INTO artikel_kategorie_shop_ausschluss (artikel_id, kategorie_id, shop_id)
                VALUES (:artikel_id, :kategorie_id, :shop_id)
            ")->execute(['artikel_id' => $artikelId, 'kategorie_id' => $kategorieId, 'shop_id' => $shopId]);
        } else {
            $this->db->prepare("
                DELETE FROM artikel_kategorie_shop_ausschluss WHERE artikel_id = :artikel_id AND kategorie_id = :kategorie_id AND shop_id = :shop_id
            ")->execute(['artikel_id' => $artikelId, 'kategorie_id' => $kategorieId, 'shop_id' => $shopId]);
        }
        $this->db->prepare("UPDATE artikel SET aktualisiert_am = NOW() WHERE id = :id")->execute(['id' => $artikelId]);
    }

    /** Ist EXAKT diese Kategorie (ohne Vorfahren) für diesen Shop ausgeschlossen? Für die
     *  Ausschluss-Prüfung eines Artikels IMMER istKategoriePfadAusgeschlossen() verwenden --
     *  diese Methode nur für die reine Statusabfrage einer einzelnen Kategorie (UI). */
    public function istKategorieAusgeschlossen(int $kategorieId, int $shopId): bool
    {
        $stmt = $this->db->prepare("SELECT ausgeschlossen FROM kategorie_shops WHERE kategorie_id = :kid AND shop_id = :sid");
        $stmt->execute(['kid' => $kategorieId, 'sid' => $shopId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Ist diese Kategorie ODER einer ihrer Vorfahren für diesen Shop ausgeschlossen? Ein
     * Ausschluss auf einer Oberkategorie ("Wurzel gesperrt") muss auf alle Blatt-Kategorien
     * darunter durchschlagen -- Artikel hängen ja nur an der Blatt-Kategorie (artikel_kategorien),
     * eine reine Prüfung der Blatt-Kategorie selbst hätte einen Wurzel-Ausschluss schlicht ignoriert
     * (echter Bug bis 2026-08-02: Jacky sperrte eine Wurzelkategorie, es passierte nichts).
     */
    public function istKategoriePfadAusgeschlossen(int $kategorieId, int $shopId): bool
    {
        foreach ($this->findKategorieMitVorfahren($kategorieId) as $kat) {
            if ($this->istKategorieAusgeschlossen((int)$kat['id'], $shopId)) {
                return true;
            }
            if ($this->istAktionskategorieAktuellUnsichtbar((int)$kat['id'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Aktionskategorien (kategorien.ist_aktions_kategorie) existieren im Shop
     * nur, solange mindestens eine ihrer Aktionen tatsächlich läuft (gestartet=1,
     * heute zwischen gueltig_ab/gueltig_bis) -- unabhängig vom manuellen
     * Ausschluss-Flag oben, das bewusst eine eigene, nur vom Menschen gesetzte
     * Einstellung bleibt. Kategorie-Term selbst wird dadurch NICHT gelöscht
     * (gleiches Prinzip wie beim manuellen Ausschluss) -- Artikel verlieren nur
     * die Zuordnung und bleiben über ihre reguläre(n) Kategorie(n) sichtbar.
     */
    private function istAktionskategorieAktuellUnsichtbar(int $kategorieId): bool
    {
        $stmt = $this->db->prepare("
            SELECT k.ist_aktions_kategorie,
                   EXISTS (
                       SELECT 1 FROM aktionen_kategorien ak
                       JOIN aktionen a ON a.id = ak.aktion_id AND a.gestartet = 1
                       WHERE ak.kategorie_id = k.id
                         AND CURDATE() BETWEEN ak.gueltig_ab AND ak.gueltig_bis
                   ) AS hat_laufende_aktion
            FROM kategorien k
            WHERE k.id = :id
        ");
        $stmt->execute(['id' => $kategorieId]);
        $row = $stmt->fetch();
        if (!$row || !$row['ist_aktions_kategorie']) {
            return false;
        }
        return !$row['hat_laufende_aktion'];
    }

    /**
     * Diese Kategorie-ID + alle Unterkategorien (rekursiv), für die Fällig-Markierung beim
     * Ausschluss/Freigeben -- siehe setKategorieAusschluss(). Reine PHP-Traversierung statt
     * rekursivem SQL (Tiefe ist mit ~4 Ebenen klein, und nicht jede MariaDB-Version hier
     * unterstützt WITH RECURSIVE zuverlässig).
     */
    private function findKategorieIdsMitUnterkategorien(int $kategorieId): array
    {
        $alle = $this->db->query("SELECT id, parent_id FROM kategorien")->fetchAll();
        $kinderVonId = [];
        foreach ($alle as $k) {
            $kinderVonId[(int)($k['parent_id'] ?? 0)][] = (int)$k['id'];
        }

        $ids   = [$kategorieId];
        $queue = [$kategorieId];
        while ($queue) {
            $aktuelleId = array_shift($queue);
            foreach ($kinderVonId[$aktuelleId] ?? [] as $kindId) {
                $ids[]   = $kindId;
                $queue[] = $kindId;
            }
        }
        return $ids;
    }

    /**
     * Schließt eine Kategorie für einen bestimmten Shop aus (oder hebt den Ausschluss wieder auf).
     * Legt bei Bedarf die kategorie_shops-Zeile neu an (kann VOR dem ersten Sync gesetzt werden,
     * die Zeile existiert sonst erst nach der ersten erfolgreichen WooCommerce-Anlage).
     * Markiert danach alle betroffenen Artikel als fällig, damit ihr Kategorie-Payload beim
     * nächsten Sync-Lauf tatsächlich aktualisiert wird -- reines Setzen des Flags allein würde
     * sonst erst beim nächsten OHNEHIN fälligen Sync des Artikels wirksam (gleiche Lehre wie bei
     * Kategorie-Umbenennungen: Change-Detection die nicht an artikel.aktualisiert_am hängt,
     * braucht einen eigenen Auslöser). WICHTIG: betrifft die Kategorie SELBST + alle
     * Unterkategorien -- ein Ausschluss auf einer Wurzelkategorie muss auch die Artikel treffen,
     * die an einer Blatt-Kategorie weiter unten hängen (Artikel sind nie direkt an der Wurzel).
     */
    public function setKategorieAusschluss(int $kategorieId, int $shopId, bool $ausgeschlossen): void
    {
        $this->db->prepare("
            INSERT INTO kategorie_shops (kategorie_id, shop_id, ausgeschlossen)
            VALUES (:kid, :sid, :aus)
            ON DUPLICATE KEY UPDATE ausgeschlossen = VALUES(ausgeschlossen)
        ")->execute(['kid' => $kategorieId, 'sid' => $shopId, 'aus' => $ausgeschlossen ? 1 : 0]);

        $betroffeneIds = $this->findKategorieIdsMitUnterkategorien($kategorieId);
        $platzhalter   = implode(',', array_fill(0, count($betroffeneIds), '?'));
        $this->db->prepare("
            UPDATE artikel a
            JOIN artikel_kategorien ak ON ak.artikel_id = a.id
            SET a.aktualisiert_am = NOW()
            WHERE ak.kategorie_id IN ($platzhalter)
        ")->execute($betroffeneIds);
    }

    /**
     * Aktionskategorien, deren Sichtbarkeits-Fenster (Start ODER Ende, siehe
     * aktionen_kategorien.gueltig_ab/gueltig_bis) seit dem letzten Abgleich
     * eine Grenze überschritten hat -- ohne diese Prüfung würde eine Kategorie
     * erst dann im Shop auftauchen/verschwinden, wenn ohnehin ein Artikel aus
     * anderem Grund fällig wird (gleiches Muster wie bei Kategorien/Herstellern/
     * Achsenwerten). Selbstbegrenzend über kategorie_shops.aktion_sichtbarkeit_
     * synced_am (Migration 160, bewusst eine EIGENE Spalte -- nicht das
     * bestehende synced_at, das den Kategorie-TERM selbst trackt und durch eine
     * unabhängige Namens-/Beschreibungsänderung einen falschen "schon erledigt"-
     * Stand vortäuschen könnte): nach markiereAktionskategorieUebergang() steht
     * der Zeitstempel hinter der überschrittenen Grenze, dieselbe Grenze wird
     * also nicht nochmal erkannt.
     */
    public function findFaelligeAktionskategorien(int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT ak.kategorie_id
            FROM aktionen_kategorien ak
            JOIN aktionen a ON a.id = ak.aktion_id AND a.gestartet = 1
            JOIN kategorien k ON k.id = ak.kategorie_id AND k.ist_aktions_kategorie = 1
            LEFT JOIN kategorie_shops ks ON ks.kategorie_id = ak.kategorie_id AND ks.shop_id = :shop_id
            WHERE
                (ak.gueltig_ab <= CURDATE()
                    AND (ks.aktion_sichtbarkeit_synced_am IS NULL OR ks.aktion_sichtbarkeit_synced_am < ak.gueltig_ab))
                OR
                (ak.gueltig_bis < CURDATE()
                    AND (ks.aktion_sichtbarkeit_synced_am IS NULL OR ks.aktion_sichtbarkeit_synced_am <= ak.gueltig_bis))
        ");
        $stmt->bindValue('shop_id', $shopId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Markiert den Sichtbarkeits-Übergang einer Aktionskategorie als erledigt
     * (Zeitstempel für findFaelligeAktionskategorien()) UND alle betroffenen
     * Artikel (inkl. Unterkategorien) als fällig -- gleiches Prinzip wie
     * setKategorieAusschluss().
     */
    public function markiereAktionskategorieUebergang(int $kategorieId, int $shopId): void
    {
        $this->db->prepare("
            INSERT INTO kategorie_shops (kategorie_id, shop_id, aktion_sichtbarkeit_synced_am)
            VALUES (:kid, :sid, NOW())
            ON DUPLICATE KEY UPDATE aktion_sichtbarkeit_synced_am = NOW()
        ")->execute(['kid' => $kategorieId, 'sid' => $shopId]);

        $betroffeneIds = $this->findKategorieIdsMitUnterkategorien($kategorieId);
        $platzhalter   = implode(',', array_fill(0, count($betroffeneIds), '?'));
        $this->db->prepare("
            UPDATE artikel a
            JOIN artikel_kategorien ak ON ak.artikel_id = a.id
            SET a.aktualisiert_am = NOW()
            WHERE ak.kategorie_id IN ($platzhalter)
        ")->execute($betroffeneIds);
    }

    /** Alle Shops mit Ausschluss-Status für eine Kategorie (für die Verwaltungs-UI). */
    public function findAusschluesseFuerKategorie(int $kategorieId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.id AS shop_id, s.name, ks.ausgeschlossen
            FROM shops s
            LEFT JOIN kategorie_shops ks ON ks.shop_id = s.id AND ks.kategorie_id = :kid
            WHERE s.ist_aktiv = 1
            ORDER BY s.id
        ");
        $stmt->execute(['kid' => $kategorieId]);
        return $stmt->fetchAll();
    }

    /** Kategorie-IDs, die direkt am Artikel hängen (nur Blatt-Kategorien, siehe artikel_kategorien). */
    public function findKategorieIdsFuerArtikel(int $artikelId): array
    {
        $stmt = $this->db->prepare("SELECT kategorie_id FROM artikel_kategorien WHERE artikel_id = :artikel_id");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Eine Kategorie + all ihre Vorfahren, Wurzel zuerst — für den vollen Pfad beim
     * WooCommerce-Sync (Entscheidung: kompletter Pfad wird über `parent` angelegt,
     * dem Artikel wird aber nur die Blatt-Kategorie zugewiesen).
     */
    public function findKategorieMitVorfahren(int $kategorieId): array
    {
        $pfad = [];
        $stmt = $this->db->prepare("SELECT id, name, beschreibung, bild_pfad, parent_id, aktualisiert_am FROM kategorien WHERE id = :id");
        $aktuelleId = $kategorieId;
        while ($aktuelleId !== null) {
            $stmt->execute(['id' => $aktuelleId]);
            $row = $stmt->fetch();
            if (!$row) break;
            array_unshift($pfad, $row);
            $aktuelleId = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
        }
        return $pfad;
    }

    /**
     * Kategorien, die für diesen Shop bereits angelegt sind, sich seit dem
     * letzten Sync aber geändert haben (Change-Detection unabhängig von
     * Artikel-Fälligkeit -- eine reine Beschreibungs-/Namensänderung an einer
     * Kategorie ohne gerade fälligen Artikel würde sonst nie nachgezogen).
     *
     * @return int[] kategorie_id
     */
    public function findFaelligeKategorien(int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT ks.kategorie_id
            FROM kategorie_shops ks
            JOIN kategorien k ON k.id = ks.kategorie_id
            WHERE ks.shop_id = :shop_id
              AND ks.externe_kategorie_id IS NOT NULL
              AND ks.ausgeschlossen = 0
              AND (ks.synced_at IS NULL OR k.aktualisiert_am > ks.synced_at)
        ");
        $stmt->execute(['shop_id' => $shopId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Bestehende Shop-Zuordnung einer Kategorie (für Idempotenz: schon angelegt = nicht nochmal). */
    public function findKategorieShopZuweisung(int $kategorieId, int $shopId): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM kategorie_shops WHERE kategorie_id = :kategorie_id AND shop_id = :shop_id");
        $stmt->execute(['kategorie_id' => $kategorieId, 'shop_id' => $shopId]);
        $row = $stmt->fetch();
        return $row ?: false;
    }

    /** Kategorie-Zuweisung nach erfolgreichem WooCommerce-Anlegen speichern. */
    public function upsertKategorieZuweisung(int $kategorieId, int $shopId, string $externeKategorieId): void
    {
        $this->db->prepare("
            INSERT INTO kategorie_shops (kategorie_id, shop_id, externe_kategorie_id, synced_at)
            VALUES (:kategorie_id, :shop_id, :externe_kategorie_id, NOW())
            ON DUPLICATE KEY UPDATE externe_kategorie_id = VALUES(externe_kategorie_id), synced_at = NOW()
        ")->execute(['kategorie_id' => $kategorieId, 'shop_id' => $shopId, 'externe_kategorie_id' => $externeKategorieId]);
    }

    /**
     * Merkt sich, welcher Bild-Dateiname zuletzt zu diesem Shop hochgeladen wurde
     * (Change-Detection -- kein Re-Upload bei jedem Cron-Lauf) + die dabei
     * vergebene WordPress-Medien-ID. $bildPfad = null heißt: Bild wurde entfernt.
     */
    public function markiereKategorieBildSynced(int $kategorieId, int $shopId, ?string $bildPfad, ?string $externalId): void
    {
        $this->db->prepare("
            UPDATE kategorie_shops
            SET bild_pfad_synced = :bild_pfad, bild_external_id = :external_id
            WHERE kategorie_id = :kategorie_id AND shop_id = :shop_id
        ")->execute([
            'bild_pfad'    => $bildPfad,
            'external_id'  => $externalId,
            'kategorie_id' => $kategorieId,
            'shop_id'      => $shopId,
        ]);
    }

    /**
     * Artikel, die im Kanal deaktiviert wurden, aber schon live in WooCommerce
     * stehen (externe_id vorhanden) -- müssen dort auf 'draft' gesetzt werden,
     * sonst bleiben sie für immer sichtbar/kaufbar (Fund 06.08.2026:
     * findFaelligeArtikel() verlangt ash.aktiv=1, ein deaktivierter Artikel
     * wurde dadurch nie wieder angefasst, auch nicht zum Abmelden). Eigener,
     * unabhängiger Durchlauf in syncShop() -- gleiches Muster wie bei
     * Kategorien/Herstellern/Achsenwerten.
     */
    public function findKuerzlichDeaktivierte(int $shopId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT ash.id AS artikel_shop_id, ash.external_id, a.id AS artikel_id,
                   a.vaterartikel_id, ash_vater.external_id AS vater_external_id
            FROM artikel_shops ash
            JOIN artikel a ON a.id = ash.artikel_id
            LEFT JOIN artikel_shops ash_vater
                   ON ash_vater.shop_id = ash.shop_id AND ash_vater.artikel_id = a.vaterartikel_id
            WHERE ash.shop_id = :shop_id
              AND ash.aktiv = 0
              AND ash.external_id IS NOT NULL
              AND ash.sync_status = 'pending'
            LIMIT :limit
        ");
        $stmt->bindValue('shop_id', $shopId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function markiereSynced(int $artikelShopId, string $externalId): void
    {
        $this->db->prepare("
            UPDATE artikel_shops
            SET external_id = :external_id, sync_status = 'synced', synced_at = NOW(), fehler_meldung = NULL
            WHERE id = :id
        ")->execute(['external_id' => $externalId, 'id' => $artikelShopId]);
    }

    public function markiereFehler(int $artikelShopId, string $fehlermeldung): void
    {
        $this->db->prepare("
            UPDATE artikel_shops
            SET sync_status = 'error', fehler_meldung = :fehler
            WHERE id = :id
        ")->execute(['fehler' => $fehlermeldung, 'id' => $artikelShopId]);
    }

    /**
     * 'error'-Zeilen ohne external_id -- genau die Fälle, bei denen unklar ist,
     * ob WooCommerce das Objekt trotz einer bei uns als Fehler angekommenen
     * Antwort (Timeout/502) doch schon angelegt hat (siehe
     * ShopSyncService::reconciliereOffeneFehler()). Eine Zeile MIT external_id
     * war ein Update, kein Create -- da gibt es kein Duplikat-Risiko, darum
     * hier bewusst ausgeschlossen.
     */
    public function findOffeneFehlerOhneExternalId(int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT ash.id AS artikel_shop_id, a.id AS artikel_id, a.artikelnummer,
                   a.vaterartikel_id, ash_vater.external_id AS vater_external_id
            FROM artikel_shops ash
            JOIN artikel a ON a.id = ash.artikel_id
            LEFT JOIN artikel_shops ash_vater
                   ON ash_vater.shop_id = ash.shop_id AND ash_vater.artikel_id = a.vaterartikel_id
            WHERE ash.shop_id = :shop_id AND ash.sync_status = 'error' AND ash.external_id IS NULL
            ORDER BY a.id
        ");
        $stmt->bindValue('shop_id', $shopId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Kanal-Chip im Artikel-Formular / Massenaktion in der Artikelliste: setzt
     * den Kanal-Wunsch für EINEN Artikel -- UND sichert bei Aktivierung
     * zusätzlich die restliche Familie ab (Fund 06.08.2026: bisher völlig
     * unabhängige Zeilen für Vater/Kind -- dadurch konnten Kinder aktiv
     * werden, deren Vater nie eine eigene Zeile bekam, und blieben für immer
     * unsichtbar 'pending'):
     * - Kind aktiviert  -> Vater bekommt (falls er noch KEINE eigene Zeile
     *   hat) ebenfalls eine aktive Zeile. Nur EINE Ebene nach oben, damit das
     *   nicht versehentlich Geschwister mitzieht.
     * - Vater aktiviert -> alle seine Kinder OHNE eigene Zeile für diesen
     *   Shop bekommen ebenfalls eine aktive Zeile (analog zur "inkl. Kinder"-
     *   Logik bei der Kategorie-Zuweisung, siehe bulk_kategorie_speichern.php).
     * Deaktivieren bleibt bewusst rein lokal (kein Herunterziehen von Vater
     * oder Geschwistern) UND eine bereits bestehende Zeile wird nie durch die
     * Kaskade überschrieben -- ein bewusst ausgeschalteter Familienteil bleibt
     * ausgeschaltet.
     */
    public function upsertZuweisung(int $artikelId, int $shopId, bool $aktiv): void
    {
        $this->schreibeZuweisungsZeile($artikelId, $shopId, $aktiv);

        if (!$aktiv) {
            return;
        }

        $vaterId = $this->holeVaterId($artikelId);
        if ($vaterId !== null) {
            if (!$this->hatZuweisungsZeile($vaterId, $shopId)) {
                $this->schreibeZuweisungsZeile($vaterId, $shopId, true);
            }
            return;
        }

        foreach ($this->findKinderOhneZuweisungsZeile($artikelId, $shopId) as $kindId) {
            $this->schreibeZuweisungsZeile($kindId, $shopId, true);
        }
    }

    private function schreibeZuweisungsZeile(int $artikelId, int $shopId, bool $aktiv): void
    {
        $this->db->prepare("
            INSERT INTO artikel_shops (artikel_id, shop_id, aktiv, sync_status)
            VALUES (:artikel_id, :shop_id, :aktiv, 'pending')
            ON DUPLICATE KEY UPDATE aktiv = VALUES(aktiv), sync_status = 'pending'
        ")->execute(['artikel_id' => $artikelId, 'shop_id' => $shopId, 'aktiv' => $aktiv ? 1 : 0]);
    }

    private function holeVaterId(int $artikelId): ?int
    {
        $stmt = $this->db->prepare("SELECT vaterartikel_id FROM artikel WHERE id = :id");
        $stmt->execute(['id' => $artikelId]);
        $vaterId = $stmt->fetchColumn();
        return $vaterId ? (int)$vaterId : null;
    }

    private function hatZuweisungsZeile(int $artikelId, int $shopId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1 FROM artikel_shops WHERE artikel_id = :artikel_id AND shop_id = :shop_id
        ");
        $stmt->execute(['artikel_id' => $artikelId, 'shop_id' => $shopId]);
        return (bool)$stmt->fetchColumn();
    }

    private function findKinderOhneZuweisungsZeile(int $vaterId, int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT k.id FROM artikel k
            WHERE k.vaterartikel_id = :vater_id AND k.aktiv = 1
              AND NOT EXISTS (
                  SELECT 1 FROM artikel_shops ash WHERE ash.artikel_id = k.id AND ash.shop_id = :shop_id
              )
        ");
        $stmt->execute(['vater_id' => $vaterId, 'shop_id' => $shopId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Kanal-Status je aktivem Shop für einen Artikel (für den Kanal-Dropdown im Formular).
     *
     * Kein Vater/Kind-Feld wird kaskadierend überschrieben — ein Kind behält immer
     * seinen eigenen "Wunsch"-Status (`eigener_status`). Effektiv sichtbar im Shop ist
     * ein Kind nur, wenn ZUSÄTZLICH der Vater dort aktiv ist (`vater_status`), sonst
     * bleibt der Wunsch gespeichert und greift automatisch, sobald der Vater wieder an ist.
     * Bei Vater-/Standalone-Artikeln ist `vater_status` immer 1 (kein Elternteil, keine Sperre).
     */
    public function findKanalStatusFuerArtikel(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.id AS shop_id, s.slug, s.name,
                   COALESCE(ash.aktiv, 0) AS eigener_status,
                   ash.sync_status, ash.fehler_meldung,
                   IF(a.vaterartikel_id IS NULL, 1, COALESCE(ash_vater.aktiv, 0)) AS vater_status
            FROM shops s
            LEFT JOIN artikel_shops ash ON ash.shop_id = s.id AND ash.artikel_id = :artikel_id
            JOIN artikel a ON a.id = :artikel_id2
            LEFT JOIN artikel_shops ash_vater ON ash_vater.shop_id = s.id AND ash_vater.artikel_id = a.vaterartikel_id
            WHERE s.ist_aktiv = 1
            ORDER BY s.id
        ");
        $stmt->execute(['artikel_id' => $artikelId, 'artikel_id2' => $artikelId]);
        return $stmt->fetchAll();
    }

    /** Polling-Cursor nach erfolgreichem Bestellungs-Sync-Durchlauf setzen. */
    public function setzeBestellungenLetzterSync(int $shopId, string $zeitpunkt): void
    {
        $this->db->prepare("UPDATE shops SET bestellungen_letzter_sync = :zeitpunkt WHERE id = :shop_id")
            ->execute(['zeitpunkt' => $zeitpunkt, 'shop_id' => $shopId]);
    }

    /** Artikel-ID zu einer WooCommerce-Bestellpositions-SKU (= unsere artikelnummer). */
    public function findArtikelIdFuerSku(string $sku): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM artikel WHERE artikelnummer = :sku LIMIT 1");
        $stmt->execute(['sku' => $sku]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    /**
     * ID des Divers-Platzhalter-Artikels (99-9999), Fallback für
     * Bestellpositionen ohne SKU-Treffer -- gleicher Mechanismus wie
     * KassenService::getDiversArtikelId()/MesseSyncService.
     */
    public function findDiversArtikelId(): ?int
    {
        $stmt = $this->db->query("SELECT id FROM artikel WHERE artikelnummer = '99-9999' LIMIT 1");
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    /** Phase 4: bereits verknüpfter Kunde über die WooCommerce-Kunden-ID (schnellster Pfad). */
    public function findKundeIdFuerShopExternalId(int $shopId, string $externalId): ?int
    {
        $stmt = $this->db->prepare("SELECT kunde_id FROM kunden_shops WHERE shop_id = :shop_id AND external_id = :external_id");
        $stmt->execute(['shop_id' => $shopId, 'external_id' => $externalId]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    /** Verknüpfung Kunde↔Shop anlegen/aktualisieren (kunden_shops existiert schon seit Migration 047). */
    public function upsertKundenShopZuweisung(int $kundeId, int $shopId, ?string $externalId): void
    {
        $this->db->prepare("
            INSERT INTO kunden_shops (kunde_id, shop_id, external_id, sync_status, synced_at)
            VALUES (:kunde_id, :shop_id, :external_id, 'synced', NOW())
            ON DUPLICATE KEY UPDATE external_id = VALUES(external_id), sync_status = 'synced', synced_at = NOW()
        ")->execute(['kunde_id' => $kundeId, 'shop_id' => $shopId, 'external_id' => $externalId]);
    }
}
