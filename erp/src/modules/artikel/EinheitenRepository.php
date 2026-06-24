<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * EinheitenRepository – Lesezugriff auf die Einheiten-Tabelle
 *
 * Einheiten sind Maßeinheiten für Artikel (z.B. "Stück", "Meter", "Gramm").
 * Werden in Dropdown-Menüs beim Artikel anlegen/bearbeiten gelistet.
 * Sortierung nach sortierung-Feld, dann alphabetisch.
 */
class EinheitenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Gibt alle Einheiten zurück, sortiert nach sortierung und Name. */
    public function findAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                id,
                name,
                kuerzel
            FROM einheiten
            ORDER BY sortierung, name ASC
        ");
        return $stmt->fetchAll();
    }
}
