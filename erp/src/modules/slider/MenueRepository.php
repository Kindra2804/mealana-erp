<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * MenueRepository – reiner DB-Zugriff für das Shop-Menü (Tabelle shop_menue_punkte, Migration 226).
 * Gemeinsame Abfragen (Shops, Kategorien, Hersteller, Woo-IDs) liegen im SliderRepository.
 */
class MenueRepository
{
    private PDO $db;

    public const FELDER = ['shop_id', 'parent_id', 'typ', 'ziel_id', 'url', 'bezeichnung', 'unterkategorien_auto', 'nur_mit_artikeln', 'spalten', 'ist_aktiv'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Alle Menüpunkte eines Shops mit dem Namen des Ziels, in Menü-Reihenfolge je Ebene. */
    public function findByShop(int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT m.*,
                   CASE m.typ
                       WHEN 'kategorie'  THEN (SELECT name FROM kategorien WHERE id = m.ziel_id)
                       WHEN 'hersteller' THEN (SELECT name FROM hersteller WHERE id = m.ziel_id)
                       WHEN 'artikel'    THEN (SELECT name FROM artikel WHERE id = m.ziel_id)
                   END AS ziel_name
            FROM shop_menue_punkte m
            WHERE m.shop_id = :shop
            ORDER BY m.sortierung, m.id
        ");
        $stmt->execute(['shop' => $shopId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM shop_menue_punkte WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /** Ebene eines Punkts (1 = Hauptpunkt). */
    public function ebene(?int $id): int
    {
        $ebene = 0;
        while ($id && $ebene < 10) {
            $stmt = $this->db->prepare("SELECT parent_id FROM shop_menue_punkte WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $id = $stmt->fetchColumn() ?: null;
            $ebene++;
        }
        return $ebene;
    }

    /** Tiefe des Teilbaums unter einem Punkt (0 = keine Unterpunkte). */
    public function teilbaumTiefe(int $id): int
    {
        $stmt = $this->db->prepare("SELECT id FROM shop_menue_punkte WHERE parent_id = :id");
        $stmt->execute(['id' => $id]);
        $max = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $kind) $max = max($max, 1 + $this->teilbaumTiefe((int)$kind));
        return $max;
    }

    public function insert(array $d): int
    {
        $d = array_intersect_key($d, array_flip(self::FELDER));
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(sortierung), 0) + 10 FROM shop_menue_punkte WHERE shop_id = :s AND parent_id <=> :p");
        $stmt->execute(['s' => $d['shop_id'], 'p' => $d['parent_id'] ?? null]);
        $d['sortierung'] = (int)$stmt->fetchColumn();
        $sp = array_keys($d);
        $this->db->prepare('INSERT INTO shop_menue_punkte (' . implode(', ', $sp) . ') VALUES (:' . implode(', :', $sp) . ')')->execute($d);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $d = array_intersect_key($d, array_flip(self::FELDER));
        unset($d['shop_id']);
        $set = implode(', ', array_map(fn($s) => "$s = :$s", array_keys($d)));
        $d['id'] = $id;
        $this->db->prepare("UPDATE shop_menue_punkte SET $set WHERE id = :id")->execute($d);
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM shop_menue_punkte WHERE id = :id")->execute(['id' => $id]); // Unterpunkte per FK-Cascade
    }

    public function sortiere(array $ids): void
    {
        $stmt = $this->db->prepare("UPDATE shop_menue_punkte SET sortierung = :s WHERE id = :id");
        foreach (array_values($ids) as $i => $id) $stmt->execute(['s' => ($i + 1) * 10, 'id' => (int)$id]);
    }

    public function anzahl(int $shopId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM shop_menue_punkte WHERE shop_id = :s");
        $stmt->execute(['s' => $shopId]);
        return (int)$stmt->fetchColumn();
    }

    // ── Shop-Stand ──────────────────────────────────────────────────────────

    public function markiereGeaendert(int $shopId): void
    {
        $this->db->prepare("UPDATE shops SET menue_geaendert = 1 WHERE id = :id")->execute(['id' => $shopId]);
    }

    public function markiereUebertragen(int $shopId, ?string $fehler): void
    {
        if ($fehler === null) {
            $this->db->prepare("UPDATE shops SET menue_uebertragen_am = NOW(), menue_fehler = NULL, menue_geaendert = 0 WHERE id = :id")->execute(['id' => $shopId]);
        } else {
            $this->db->prepare("UPDATE shops SET menue_fehler = :f WHERE id = :id")->execute(['f' => mb_substr($fehler, 0, 500), 'id' => $shopId]);
        }
    }

    public function setOrte(int $shopId, string $orte): void
    {
        $this->db->prepare("UPDATE shops SET menue_orte = :o, menue_geaendert = 1 WHERE id = :id")->execute(['o' => $orte, 'id' => $shopId]);
    }

    public function findShopStand(int $shopId): array|false
    {
        $stmt = $this->db->prepare("SELECT menue_orte, menue_uebertragen_am, menue_fehler, menue_geaendert FROM shops WHERE id = :id");
        $stmt->execute(['id' => $shopId]);
        return $stmt->fetch();
    }

    // ── Kategoriebaum für automatische Unterkategorien + Vorschlag ─────────

    /**
     * Unterkategorien einer Kategorie, die im Shop existieren (Woo-ID, nicht ausgeschlossen, aktiv),
     * in der Reihenfolge des Kategoriebaums.
     */
    public function findShopUnterkategorien(int $kategorieId, int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT k.id, k.name, ks.externe_kategorie_id
            FROM kategorien k
            JOIN kategorie_shops ks ON ks.kategorie_id = k.id AND ks.shop_id = :shop
            WHERE k.parent_id = :k AND k.aktiv = 1 AND ks.ausgeschlossen = 0 AND ks.externe_kategorie_id IS NOT NULL
            ORDER BY k.sortierung, k.name
        ");
        $stmt->execute(['k' => $kategorieId, 'shop' => $shopId]);
        return $stmt->fetchAll();
    }

    /** Hauptkategorien des Shops (unter der Wurzelkategorie bzw. oberste Ebene), für „Aus Kategoriebaum vorschlagen“. */
    public function findShopHauptkategorien(int $shopId): array
    {
        $stmt = $this->db->prepare("SELECT wurzel_kategorie_id FROM shops WHERE id = :id");
        $stmt->execute(['id' => $shopId]);
        $wurzel = $stmt->fetchColumn() ?: null;
        $stmt = $this->db->prepare("
            SELECT k.id, k.name
            FROM kategorien k
            JOIN kategorie_shops ks ON ks.kategorie_id = k.id AND ks.shop_id = :shop
            WHERE k.parent_id <=> :wurzel AND k.aktiv = 1 AND ks.ausgeschlossen = 0 AND ks.externe_kategorie_id IS NOT NULL
            ORDER BY k.sortierung, k.name
        ");
        $stmt->execute(['shop' => $shopId, 'wurzel' => $wurzel]);
        return $stmt->fetchAll();
    }

    /**
     * Wurde der Kategoriebaum seit der letzten Übertragung geändert? (für den Cron)
     * Auch neu im Shop angelegte Kategorien zählen (kategorie_shops.synced_at) — eine neue
     * Kategorie hat ihre Woo-ID erst nach dem Kategorie-Sync.
     */
    public function kategorienGeaendertSeit(string $zeitpunkt, int $shopId): bool
    {
        $stmt = $this->db->prepare("
            SELECT (SELECT COUNT(*) FROM kategorien WHERE aktualisiert_am > :z1)
                 + (SELECT COUNT(*) FROM kategorie_shops WHERE shop_id = :s AND synced_at > :z2)
        ");
        $stmt->execute(['z1' => $zeitpunkt, 'z2' => $zeitpunkt, 's' => $shopId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
