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
            aa.sort_order
        FROM artikel_achsen aa
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

        return $stmt->rowCount() > 0;
    }
}
