<?php

require_once __DIR__ . '/../../core/Database.php';

class KategorieRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                id,
                name
            FROM kategorien
            WHERE aktiv = 1
            ORDER BY name ASC
        ");
        return $stmt->fetchAll();
    }

    public function findAllMitEltern(): array
    {
        $stmt = $this->db->query("
            SELECT k.id, k.parent_id, k.name, k.sortierung,
                COUNT(DISTINCT ak.artikel_id) AS artikel_anzahl
            FROM kategorien k
            LEFT JOIN artikel_kategorien ak ON ak.kategorie_id = k.id
            LEFT JOIN artikel a ON a.id = ak.artikel_id
                AND a.aktiv = 1
                AND a.vaterartikel_id IS NULL
                AND a.zustand_vater_id IS NULL
            WHERE k.aktiv = 1
            GROUP BY k.id
            ORDER BY k.sortierung ASC, k.name ASC
        ");
        return $stmt->fetchAll();
    }

    public function countByKategorie(int $kategorieId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT a.id)
            FROM artikel a
            INNER JOIN artikel_kategorien ak ON ak.artikel_id = a.id
            WHERE ak.kategorie_id = :kid
              AND a.aktiv = 1
              AND a.vaterartikel_id IS NULL
              AND a.zustand_vater_id IS NULL
        ");
        $stmt->execute(['kid' => $kategorieId]);
        return (int) $stmt->fetchColumn();
    }

    public function findByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                k.id, 
                k.name 
            FROM kategorien k
            INNER JOIN artikel_kategorien ak ON k.id = ak.kategorie_id
            WHERE ak.artikel_id = :artikel_id
            AND k.aktiv = 1
            ORDER BY k.name ASC
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    public function updateArtikelKategoriezuweisungen(int $artikelId, array $kategorieIds): void
    {
        // Alle bestehenden Zuweisungen löschen
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("DELETE FROM artikel_kategorien WHERE artikel_id = :artikel_id");
            $stmt->execute(['artikel_id' => $artikelId]);

            // Neue Zuweisungen einfügen
            $stmt = $this->db->prepare("INSERT INTO artikel_kategorien (artikel_id, kategorie_id) VALUES (:artikel_id, :kategorie_id)");
            foreach ($kategorieIds as $kategorieId) {
                $stmt->execute([
                    'artikel_id' => $artikelId,
                    'kategorie_id' => $kategorieId
                ]);
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e; // Fehler weiterwerfen oder entsprechend behandeln
        }
    }

    public function insert(string $name, ?int $parentId = null): int
    {
        $stmt = $this->db->prepare("INSERT INTO kategorien (name, parent_id) VALUES (:name, :parent_id)");
        $stmt->execute(['name' => $name, 'parent_id' => $parentId]);
        return (int) $this->db->lastInsertId();
    }
}
