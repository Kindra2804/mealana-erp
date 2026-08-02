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

    /** Wie findAll(), zusätzlich mit Anzahl Artikel je Einheit (für die Verwaltungs-UI --
     *  eine verwendete Einheit darf nicht gelöscht werden, siehe delete()). */
    public function findAllMitVerwendung(): array
    {
        $stmt = $this->db->query("
            SELECT
                e.id,
                e.name,
                e.kuerzel,
                e.sortierung,
                COUNT(a.id) AS artikel_anzahl
            FROM einheiten e
            LEFT JOIN artikel a ON a.einheit_id = e.id
            GROUP BY e.id, e.name, e.kuerzel, e.sortierung
            ORDER BY e.sortierung, e.name ASC
        ");
        return $stmt->fetchAll();
    }

    public function insert(string $name, ?string $kuerzel, int $sortierung = 0): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO einheiten (name, kuerzel, sortierung) VALUES (:name, :kuerzel, :sortierung)
        ");
        $stmt->execute(['name' => $name, 'kuerzel' => $kuerzel ?: null, 'sortierung' => $sortierung]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $name, ?string $kuerzel, int $sortierung): void
    {
        $stmt = $this->db->prepare("
            UPDATE einheiten SET name = :name, kuerzel = :kuerzel, sortierung = :sortierung WHERE id = :id
        ");
        $stmt->execute(['name' => $name, 'kuerzel' => $kuerzel ?: null, 'sortierung' => $sortierung, 'id' => $id]);
    }

    /** Zählt Artikel, die diese Einheit verwenden (für die "in Verwendung, Löschen blockiert"-Prüfung). */
    public function zaehleVerwendung(int $id): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM artikel WHERE einheit_id = :id");
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM einheiten WHERE id = :id")->execute(['id' => $id]);
    }
}
