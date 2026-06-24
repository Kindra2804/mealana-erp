<?php

require_once __DIR__ . '/../../core/database.php';

/**
 * VariantenRepository – Datenzugriff für Achsen-Zuweisungen, Werte und Kombinationen
 *
 * Deckt drei Tabellen ab:
 *   artikel_achsen             → Welche Achsen hat dieser Vater-Artikel? (mit Bedingungs-FK)
 *   varianten_achse_werte      → Welche Werte existieren pro Achse+Artikel? (Rot, Blau, 3mm...)
 *   varianten_kombination_werte → Verknüpft Kind-Artikel (kombination_id) mit ihren Wert-IDs
 *
 * Granulare Lösch-Logik in deleteWerteExcluding():
 *   Werte die in Kombinationen verwendet werden (findWertIdsInUse) DÜRFEN NICHT gelöscht werden.
 *   Der Service schickt eine Liste von "behalten"-IDs, alles andere wird entfernt.
 *   Bei leerer excludeIds-Liste werden ALLE Werte gelöscht (deleteWerteByArtikelId).
 *
 * insertKindArtikel() ist eine vereinfachte Insert-Methode speziell für Kombinations-Kinder.
 * Die vollständigen Felder werden im Service aus dem Vater-Artikel befüllt (erstelleKombinationen).
 */
class VariantenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // Für artikel_achsen:

    public function findAchsenByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            aa.id,
            aa.artikel_id,
            aa.achse_id,
            aa.bedingungs_achse_id,
            aa.bedingungs_wert_id,
            aa.sort_order,
            va.name,
            va.code,
            va.darstellungsform,
            va.ist_gruppe,
            va.abhaengig_von_achse_id
        FROM artikel_achsen aa
        JOIN varianten_achsen va ON aa.achse_id = va.id
        WHERE aa.artikel_id = :artikel_id
        ORDER BY aa.sort_order, va.name
        ");

        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    /** Weist eine Achse einem Artikel zu (artikel_achsen). Bedingungs-FK optional für abhängige Achsen. */
    public function insertArtikelAchse(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO artikel_achsen (
                artikel_id,
                achse_id,
                bedingungs_achse_id,
                bedingungs_wert_id,
                sort_order
            )
            VALUES (
                :artikel_id,
                :achse_id,
                :bedingungs_achse_id,
                :bedingungs_wert_id,
                :sort_order
            )
        ");

        $stmt->execute([
            'artikel_id' => $data['artikel_id'],
            'achse_id' => $data['achse_id'],
            'bedingungs_achse_id' => $data['bedingungs_achse_id'] ?? null,
            'bedingungs_wert_id' => $data['bedingungs_wert_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** Löscht alle Achsen-Zuweisungen eines Artikels (vollständiger Replace-Ansatz). */
    public function deleteArtikelAchsenByArtikelId(int $artikelId): bool
    {
        $stmt = $this->db->prepare("
            DELETE
            FROM artikel_achsen
            WHERE artikel_id  = :artikel_id 
        ");

        $stmt->execute([
            'artikel_id' => $artikelId
        ]);

        return $stmt->rowCount() > 0;
    }


    //Für varianten_achse_werte:

    public function findWerteByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                vaw.id,
                vaw.artikel_id,
                vaw.achse_id,
                vaw.wert,
                vaw.wert_zusatz,
                vaw.bedingungs_wert_id,
                vaw.aufpreis,
                vaw.sort_order
            FROM varianten_achse_werte vaw
            WHERE vaw.artikel_id = :artikel_id
            ORDER BY vaw.achse_id, vaw.sort_order, vaw.wert
        ");

        $stmt->execute([
            'artikel_id' => $artikelId
        ]);

        return $stmt->fetchAll();
    }

    public function insertWert(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO varianten_achse_werte
                (artikel_id, achse_id, wert, wert_zusatz, bedingungs_wert_id, aufpreis, sort_order)
            VALUES
                (:artikel_id, :achse_id, :wert, :wert_zusatz, :bedingungs_wert_id, :aufpreis, :sort_order)
        ");

        $stmt->execute([
            'artikel_id'          => $data['artikel_id'],
            'achse_id'            => $data['achse_id'],
            'wert'                => $data['wert'],
            'wert_zusatz'         => $data['wert_zusatz'] ?? null,
            'bedingungs_wert_id'  => $data['bedingungs_wert_id'] ?? null,
            'aufpreis'            => $data['aufpreis'] ?? 0,
            'sort_order'          => $data['sort_order'] ?? 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** Löscht ALLE Achsen-Werte eines Artikels. Nur nutzen wenn sicher keine Kombinationen existieren. */
    public function deleteWerteByArtikelId(int $artikelId): bool
    {
        $stmt = $this->db->prepare("
            DELETE
            FROM varianten_achse_werte
            WHERE artikel_id  = :artikel_id
        ");

        $stmt->execute([
            'artikel_id' => $artikelId
        ]);

        return (int) $stmt->rowCount() > 0;
    }

    /**
     * Löscht alle Werte AUSSER den angegebenen IDs.
     * excludeIds = in-use Wert-IDs (aus findWertIdsInUse) — diese dürfen nicht entfernt werden
     * weil Kombinationen (Kind-Artikel) auf sie verweisen.
     * Bei leerem $excludeIds werden alle Werte gelöscht (delegiert an deleteWerteByArtikelId).
     */
    public function deleteWerteExcluding(int $artikelId, array $excludeIds): void
    {
        if (empty($excludeIds)) {
            $this->deleteWerteByArtikelId($artikelId);
            return;
        }
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $stmt = $this->db->prepare("
            DELETE FROM varianten_achse_werte
            WHERE artikel_id = ? AND id NOT IN ($placeholders)
        ");
        $stmt->execute(array_merge([$artikelId], $excludeIds));
    }

    public function deleteArtikelAchse(int $artikelId, int $achseId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM artikel_achsen
            WHERE artikel_id = :artikel_id AND achse_id = :achse_id
        ");
        $stmt->execute(['artikel_id' => $artikelId, 'achse_id' => $achseId]);
    }

    public function deleteWert(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM varianten_achse_werte WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function updateWertSortOrder(int $id, int $sortOrder): void
    {
        $stmt = $this->db->prepare("UPDATE varianten_achse_werte SET sort_order = :sort WHERE id = :id");
        $stmt->execute(['sort' => $sortOrder, 'id' => $id]);
    }

    /**
     * Gibt alle Wert-IDs zurück die in mindestens einer Kombination (varianten_kombination_werte) verwendet werden.
     * Diese Werte sind "geschützt" und dürfen nicht gelöscht werden.
     * Wird in VariantenService::speichereAchsenUndWerte() genutzt um die Lösch-Whitelist zu bauen.
     */
    public function findWertIdsInUse(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT vaw.id
            FROM varianten_achse_werte vaw
            INNER JOIN varianten_kombination_werte vkw ON vkw.wert_id = vaw.id
            WHERE vaw.artikel_id = :artikel_id
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function isKindArtikel(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT vaterartikel_id FROM artikel WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false && $row['vaterartikel_id'] !== null;
    }

    /**
     * Gibt alle bereits existierenden Kind-Artikel mit ihren Wert-IDs zurück.
     * wert_ids als GROUP_CONCAT sortierter Strings (z.B. "3,7,12") — wird im Service
     * für den Duplikat-Check beim Kombinationsgenerator verwendet.
     */
    public function findExistingKombinationen(int $vaterId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                a.id,
                a.artikelnummer,
                a.name,
                a.aktiv,
                GROUP_CONCAT(vkw.wert_id ORDER BY vkw.wert_id) AS wert_ids
            FROM artikel a
            JOIN varianten_kombination_werte vkw ON vkw.kombination_id = a.id
            WHERE a.vaterartikel_id = :vater_id
            GROUP BY a.id, a.artikelnummer, a.name
        ");

        $stmt->execute([
            'vater_id' => $vaterId
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Lädt Wert-Datensätze anhand einer Liste von IDs.
     * Genutzt im VarKombi-Generator um Wert-Details (wert, achse_id) zu einem Wert-Set zu laden.
     */
    public function findWerteByIds(array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $this->db->prepare("
            SELECT
                vaw.id,
                vaw.artikel_id,
                vaw.achse_id,
                vaw.wert,
                vaw.wert_zusatz,
                vaw.aufpreis,
                vaw.sort_order
            FROM varianten_achse_werte vaw
            WHERE vaw.id IN ($placeholders)
        ");

        $stmt->execute($ids);

        return $stmt->fetchAll();
    }

    public function insertKindArtikel(array $kind): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO artikel (
                artikelnummer,
                name,
                steuerklasse_id,
                artikeltyp_id,
                vaterartikel_id,
                hat_eigenen_lagerstand,
                einheit_id,
                charge_pflicht
            )
            VALUES (
                :artikelnummer,
                :name,
                :steuerklasse_id,
                :artikeltyp_id,
                :vaterartikel_id,
                :hat_eigenen_lagerstand,
                :einheit_id,
                :charge_pflicht
            )
        ");

        $stmt->execute([
            'artikelnummer' => $kind['artikelnummer'],
            'name' => $kind['name'],
            'steuerklasse_id' => $kind['steuerklasse_id'],
            'artikeltyp_id' => $kind['artikeltyp_id'],
            'vaterartikel_id' => $kind['vaterartikel_id'],
            'hat_eigenen_lagerstand' => $kind['hat_eigenen_lagerstand'] ?? 0,
            'einheit_id' => $kind['einheit_id'],
            'charge_pflicht' => $kind['charge_pflicht'] ?? 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function insertKombinationWert(array $wert): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO varianten_kombination_werte  (
                kombination_id,
                wert_id
            )
            VALUES (
                :kombination_id,
                :wert_id
            )
        ");

        $stmt->execute([
            'kombination_id' => $wert['kombination_id'],
            'wert_id' => $wert['wert_id']
        ]);

        return $stmt->rowCount() > 0;
    }
}
