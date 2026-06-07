<?php

require_once __DIR__ . '/../../core/Database.php';

class EinheitenRepository
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
                name ,
                kuerzel
            FROM einheiten
            ORDER BY sortierung, name ASC
        ");
        return $stmt->fetchAll();
    }
}
