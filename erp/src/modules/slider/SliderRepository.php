<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * SliderRepository – reiner DB-Zugriff für den Shop-Slider (Tabelle shop_slides, Migration 224).
 *
 * Eine Slide gehört zu genau einem Shop und einer Position:
 *   startseite = Hero-Slider der Startseite, kategorie = Banner oben auf einer Kategorieseite.
 */
class SliderRepository
{
    private PDO $db;

    /** Felder, die aus dem Formular gespeichert werden dürfen (Whitelist). */
    public const FELDER = [
        'shop_id', 'position', 'kategorie_id', 'art', 'bezeichnung', 'kicker', 'titel', 'text',
        'button1_text', 'button1_ziel_typ', 'button1_ziel_id', 'button1_url',
        'button2_text', 'button2_ziel_typ', 'button2_ziel_id', 'button2_url',
        'text_position', 'schrift', 'alt_text', 'sichtbar', 'gueltig_von', 'gueltig_bis', 'aktion_id', 'ist_aktiv',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Shops ───────────────────────────────────────────────────────────────

    /**
     * Alle aktiven Shops (für die Shop-Auswahl) — auch ohne WooCommerce-Anbindung, damit
     * Slides schon vor dem Shop-Start vorbereitet werden können. Übertragen braucht die Anbindung.
     */
    public function findShops(): array
    {
        return $this->db->query("
            SELECT id, name, slug, wc_url, wc_key, wc_secret, wp_username, wp_app_password,
                   slider_intervall_sek, slider_uebertragen_am, slider_fehler, slider_geaendert
            FROM shops
            WHERE ist_aktiv = 1
            ORDER BY id
        ")->fetchAll();
    }

    public function findShop(int $shopId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT id, name, slug, wc_url, wc_key, wc_secret, wp_username, wp_app_password,
                   slider_intervall_sek, slider_uebertragen_am, slider_fehler, slider_geaendert
            FROM shops WHERE id = :id
        ");
        $stmt->execute(['id' => $shopId]);
        return $stmt->fetch();
    }

    /** Merkt sich, dass es im Shop nicht übertragene Änderungen gibt. */
    public function markiereGeaendert(int $shopId): void
    {
        $this->db->prepare("UPDATE shops SET slider_geaendert = 1 WHERE id = :id")->execute(['id' => $shopId]);
    }

    public function setIntervall(int $shopId, int $sekunden): void
    {
        $this->db->prepare("UPDATE shops SET slider_intervall_sek = :s, slider_geaendert = 1 WHERE id = :id")
            ->execute(['s' => $sekunden, 'id' => $shopId]);
    }

    public function markiereUebertragen(int $shopId, ?string $fehler): void
    {
        $sql = $fehler === null
            ? "UPDATE shops SET slider_uebertragen_am = NOW(), slider_fehler = NULL, slider_geaendert = 0 WHERE id = :id"
            : "UPDATE shops SET slider_fehler = :f WHERE id = :id";
        $params = ['id' => $shopId];
        if ($fehler !== null) $params['f'] = mb_substr($fehler, 0, 500);
        $this->db->prepare($sql)->execute($params);
    }

    // ── Slides ──────────────────────────────────────────────────────────────

    /**
     * Slides eines Shops, sortiert: erst Startseite, dann Kategorie-Banner (nach Kategoriename).
     * Liefert zusätzlich Kategorie- und Aktionsname für die Liste.
     */
    public function findByShop(int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, k.name AS kategorie_name, a.name AS aktion_name, a.gestartet AS aktion_gestartet,
                   (SELECT MIN(ak.gueltig_ab)  FROM aktionen_kategorien ak WHERE ak.aktion_id = s.aktion_id) AS aktion_von,
                   (SELECT MAX(ak.gueltig_bis) FROM aktionen_kategorien ak WHERE ak.aktion_id = s.aktion_id) AS aktion_bis
            FROM shop_slides s
            LEFT JOIN kategorien k ON k.id = s.kategorie_id
            LEFT JOIN aktionen a   ON a.id = s.aktion_id
            WHERE s.shop_id = :shop
            ORDER BY s.position = 'kategorie', k.name, s.sortierung, s.id
        ");
        $stmt->execute(['shop' => $shopId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM shop_slides WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /** Legt eine Slide an; sie kommt ans Ende ihrer Gruppe (Shop + Position + Kategorie). */
    public function insert(array $daten): int
    {
        $daten = array_intersect_key($daten, array_flip(self::FELDER));
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(sortierung), 0) + 10 FROM shop_slides
            WHERE shop_id = :shop AND position = :pos AND kategorie_id <=> :kat
        ");
        $stmt->execute(['shop' => $daten['shop_id'], 'pos' => $daten['position'], 'kat' => $daten['kategorie_id'] ?? null]);
        $daten['sortierung'] = (int)$stmt->fetchColumn();

        $spalten = array_keys($daten);
        $sql = 'INSERT INTO shop_slides (' . implode(', ', $spalten) . ') VALUES (:' . implode(', :', $spalten) . ')';
        $this->db->prepare($sql)->execute($daten);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $daten): void
    {
        $daten = array_intersect_key($daten, array_flip(self::FELDER));
        unset($daten['shop_id']); // Shop wechselt man per Kopieren, nicht per Bearbeiten
        $set = implode(', ', array_map(fn($s) => "$s = :$s", array_keys($daten)));
        $daten['id'] = $id;
        $this->db->prepare("UPDATE shop_slides SET $set WHERE id = :id")->execute($daten);
    }

    public function setAktiv(int $id, bool $aktiv): void
    {
        $this->db->prepare("UPDATE shop_slides SET ist_aktiv = :a WHERE id = :id")->execute(['a' => (int)$aktiv, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM shop_slides WHERE id = :id")->execute(['id' => $id]);
    }

    /** Neue Reihenfolge innerhalb einer Gruppe: $ids in der gewünschten Reihenfolge. */
    public function sortiere(array $ids): void
    {
        $stmt = $this->db->prepare("UPDATE shop_slides SET sortierung = :s WHERE id = :id");
        foreach (array_values($ids) as $i => $id) {
            $stmt->execute(['s' => ($i + 1) * 10, 'id' => (int)$id]);
        }
    }

    /** Bild-Dateiname setzen; ein neues Bild muss neu in die Mediathek (WP-Felder leeren). */
    public function setBild(int $id, string $variante, ?string $datei): void
    {
        $spalte = $variante === 'mobil' ? 'bild_mobil' : 'bild_desktop';
        $this->db->prepare("
            UPDATE shop_slides SET {$spalte} = :d, {$spalte}_wp_id = NULL, {$spalte}_wp_url = NULL, {$spalte}_synced = NULL
            WHERE id = :id
        ")->execute(['d' => $datei, 'id' => $id]);
    }

    /** Ergebnis des Mediathek-Uploads festhalten (synced = Dateiname, der hochgeladen wurde). */
    public function setBildWp(int $id, string $variante, int $wpId, string $wpUrl, string $datei): void
    {
        $spalte = $variante === 'mobil' ? 'bild_mobil' : 'bild_desktop';
        $this->db->prepare("
            UPDATE shop_slides SET {$spalte}_wp_id = :wid, {$spalte}_wp_url = :url, {$spalte}_synced = :d WHERE id = :id
        ")->execute(['wid' => $wpId, 'url' => $wpUrl, 'd' => $datei, 'id' => $id]);
    }

    // ── Auswahllisten + Zielauflösung ──────────────────────────────────────

    /**
     * Kategorien für die Auswahl: die, die im Shop schon existieren (haben eine Woo-ID).
     * Ist der Shop noch nicht befüllt (Vorbereitung vor dem Start), alle aktiven Kategorien.
     */
    public function findShopKategorien(int $shopId): array
    {
        $alle = $this->db->query("SELECT id, name, parent_id FROM kategorien WHERE aktiv = 1 ORDER BY name")->fetchAll();
        $stmt = $this->db->prepare("
            SELECT k.id, k.name, k.parent_id, ks.externe_kategorie_id
            FROM kategorien k
            JOIN kategorie_shops ks ON ks.kategorie_id = k.id AND ks.shop_id = :shop
            WHERE ks.externe_kategorie_id IS NOT NULL AND ks.ausgeschlossen = 0
            ORDER BY k.name
        ");
        $stmt->execute(['shop' => $shopId]);
        return $stmt->fetchAll() ?: $alle;
    }

    /** Alle Kategorien mit Elternteil (für Pfadanzeige), unabhängig vom Shop. */
    public function findAlleKategorien(): array
    {
        return $this->db->query("SELECT id, name, parent_id FROM kategorien")->fetchAll(PDO::FETCH_UNIQUE);
    }

    /** Hersteller, die im Shop als Filter-Begriff angelegt sind (Shop noch leer: alle). */
    public function findShopHersteller(int $shopId): array
    {
        $alle = $this->db->query("SELECT id, name FROM hersteller ORDER BY name")->fetchAll();
        $stmt = $this->db->prepare("
            SELECT h.id, h.name FROM hersteller h
            JOIN hersteller_shops hs ON hs.hersteller_id = h.id AND hs.shop_id = :shop
            WHERE hs.externe_term_id IS NOT NULL
            ORDER BY h.name
        ");
        $stmt->execute(['shop' => $shopId]);
        return $stmt->fetchAll() ?: $alle;
    }

    /** Artikel-Suche für das Button-Ziel: Vaterartikel, die für den Shop freigeschaltet sind. */
    public function sucheShopArtikel(int $shopId, string $q): array
    {
        $stmt = $this->db->prepare("
            SELECT a.id, a.artikelnummer, a.name
            FROM artikel a
            JOIN artikel_shops ash ON ash.artikel_id = a.id AND ash.shop_id = :shop AND ash.aktiv = 1
            WHERE a.vaterartikel_id IS NULL
              AND (a.artikelnummer LIKE :q OR a.name LIKE :q)
            ORDER BY a.name LIMIT 20
        ");
        $stmt->execute(['shop' => $shopId, 'q' => '%' . $q . '%']);
        return $stmt->fetchAll();
    }

    public function findArtikelName(int $id): ?string
    {
        $stmt = $this->db->prepare("SELECT CONCAT(artikelnummer, ' – ', name) FROM artikel WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchColumn() ?: null;
    }

    /** Woo-ID des Ziels im Shop (Kategorie-Term, Produkt, Hersteller-Term) oder null, wenn (noch) nicht im Shop. */
    public function findExterneZielId(string $typ, int $id, int $shopId): ?string
    {
        $sql = match ($typ) {
            'kategorie'  => "SELECT externe_kategorie_id FROM kategorie_shops WHERE kategorie_id = :id AND shop_id = :shop",
            'artikel'    => "SELECT external_id FROM artikel_shops WHERE artikel_id = :id AND shop_id = :shop",
            'hersteller' => "SELECT externe_term_id FROM hersteller_shops WHERE hersteller_id = :id AND shop_id = :shop",
            default      => null,
        };
        if ($sql === null) return null;
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'shop' => $shopId]);
        $wert = $stmt->fetchColumn();
        return $wert !== false && $wert !== null && $wert !== '' ? (string)$wert : null;
    }

    /** Aktionen für die Auswahl „solange Aktion läuft“ (mit Zeitraum aus den Aktionskategorien). */
    public function findAktionen(): array
    {
        return $this->db->query("
            SELECT a.id, a.name, a.gestartet, MIN(ak.gueltig_ab) AS von, MAX(ak.gueltig_bis) AS bis
            FROM aktionen a LEFT JOIN aktionen_kategorien ak ON ak.aktion_id = a.id
            GROUP BY a.id ORDER BY a.erstellt_am DESC
        ")->fetchAll();
    }

    public function findAktionZeitraum(int $aktionId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT a.gestartet, MIN(ak.gueltig_ab) AS von, MAX(ak.gueltig_bis) AS bis
            FROM aktionen a LEFT JOIN aktionen_kategorien ak ON ak.aktion_id = a.id
            WHERE a.id = :id GROUP BY a.id
        ");
        $stmt->execute(['id' => $aktionId]);
        return $stmt->fetch();
    }
}
