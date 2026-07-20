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

    /** Alle Shops mit konfigurierter WooCommerce-Anbindung. */
    public function findAktiveShops(): array
    {
        $stmt = $this->db->query("
            SELECT id, slug, name, wc_url, wc_key, wc_secret
            FROM shops
            WHERE ist_aktiv = 1 AND wc_url IS NOT NULL AND wc_key IS NOT NULL AND wc_secret IS NOT NULL
        ");
        return $stmt->fetchAll();
    }

    /**
     * Fällige Standard-Artikel (Typ ohne Varianten, kein Vater/Kind) für einen Shop.
     * Vater/Kind-Mapping auf WooCommerce Variable Products kommt in einer späteren Phase.
     */
    public function findFaelligeArtikel(int $shopId, int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT ash.id AS artikel_shop_id, ash.external_id, a.id AS artikel_id,
                   a.artikelnummer, a.name, a.kurzbeschreibung, a.beschreibung, a.aktiv
            FROM artikel_shops ash
            JOIN artikel a ON a.id = ash.artikel_id
            WHERE ash.shop_id = :shop_id
              AND ash.aktiv = 1
              AND a.vaterartikel_id IS NULL
              AND (
                  ash.sync_status IN ('pending', 'error')
                  OR a.aktualisiert_am > ash.synced_at
              )
            ORDER BY ash.id
            LIMIT :limit
        ");
        $stmt->bindValue('shop_id', $shopId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
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

    /** Kategorie-Namen des Artikels, die schon eine WooCommerce-Zuordnung für diesen Shop haben. */
    public function findWcKategorieIds(int $artikelId, int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT ks.externe_kategorie_id
            FROM artikel_kategorien ak
            JOIN kategorie_shops ks ON ks.kategorie_id = ak.kategorie_id AND ks.shop_id = :shop_id
            WHERE ak.artikel_id = :artikel_id AND ks.externe_kategorie_id IS NOT NULL
        ");
        $stmt->execute(['artikel_id' => $artikelId, 'shop_id' => $shopId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
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
        $stmt = $this->db->prepare("SELECT id, name, parent_id FROM kategorien WHERE id = :id");
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
            INSERT INTO kategorie_shops (kategorie_id, shop_id, externe_kategorie_id)
            VALUES (:kategorie_id, :shop_id, :externe_kategorie_id)
            ON DUPLICATE KEY UPDATE externe_kategorie_id = VALUES(externe_kategorie_id)
        ")->execute(['kategorie_id' => $kategorieId, 'shop_id' => $shopId, 'externe_kategorie_id' => $externeKategorieId]);
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

    /** Kanal-Chip im Artikel-Formular: Zuweisung anlegen/aktualisieren. */
    public function upsertZuweisung(int $artikelId, int $shopId, bool $aktiv): void
    {
        $this->db->prepare("
            INSERT INTO artikel_shops (artikel_id, shop_id, aktiv, sync_status)
            VALUES (:artikel_id, :shop_id, :aktiv, 'pending')
            ON DUPLICATE KEY UPDATE aktiv = VALUES(aktiv), sync_status = 'pending'
        ")->execute(['artikel_id' => $artikelId, 'shop_id' => $shopId, 'aktiv' => $aktiv ? 1 : 0]);
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
}
