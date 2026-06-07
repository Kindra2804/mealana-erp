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

    public function insert(string $name): int
    {
        $stmt = $this->db->prepare("INSERT INTO kategorien (name) VALUES (:name)");
        $stmt->execute(['name' => $name]);

        return $this->db->lastInsertId();
    }
}
