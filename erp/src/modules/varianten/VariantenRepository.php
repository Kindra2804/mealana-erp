<?php

require_once __DIR__ . '/../../core/database.php';

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
            va.darstellungsform
        FROM artikel_achsen aa
        JOIN varianten_achsen va ON aa.achse_id= va.id
        WHERE aa.artikel_id = :artikel_id
        ");

        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    public function insertArtikelAchse(array $data): int //eine Achse zuweisen
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

    public function deleteArtikelAchsenByArtikelId(int $artikelId): bool //alle Achsen löschen (für Replace)
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
                vaw.aufpreis,
                vaw.sort_order
            FROM varianten_achse_werte vaw
            WHERE vaw.artikel_id = :artikel_id
            ORDER BY vaw.sort_order, vaw.wert
        ");

        $stmt->execute([
            'artikel_id' => $artikelId
        ]);

        return $stmt->fetchAll();
    }

    public function insertWert(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO varianten_achse_werte (
                artikel_id,
                achse_id,
                wert,
                wert_zusatz,
                aufpreis,
                sort_order
            )
            VALUES (
                :artikel_id,
                :achse_id,
                :wert,
                :wert_zusatz,
                :aufpreis,
                :sort_order
            )
        ");

        $stmt->execute([
            'artikel_id' => $data['artikel_id'],
            'achse_id' => $data['achse_id'],
            'wert' => $data['wert'],
            'wert_zusatz' => $data['wert_zusatz'] ?? null,
            'aufpreis' => $data['aufpreis'] ?? 0,
            'sort_order' => $data['sort_order'] ?? 0
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function deleteWerteByArtikelId(int $artikelId): bool //alle Werte löschen (für Replace)
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

    public function findWerteByIds(array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        // Bei [1, 3] → "?,?"

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
