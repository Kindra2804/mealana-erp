<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * RollenRepository – Datenzugriff für die Rollen/Berechtigungen-Matrix
 */
class RollenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Gibt alle Rollen zurück, absteigend nach Rang (höchste zuerst). */
    public function findAlleRollen(): array
    {
        $stmt = $this->db->query("SELECT id, name, beschreibung, rang, aktiv FROM rollen ORDER BY rang DESC");
        return $stmt->fetchAll();
    }

    /** Gibt alle Berechtigungen zurück, alphabetisch (Modul-Präfix gruppiert sich dadurch von selbst). */
    public function findAlleBerechtigungen(): array
    {
        $stmt = $this->db->query("SELECT id, name, beschreibung FROM berechtigungen WHERE aktiv = 1 ORDER BY name");
        return $stmt->fetchAll();
    }

    /** Gibt alle rollen_berechtigungen-Zuweisungen zurück (für den Matrix-Lookup). */
    public function findMatrix(): array
    {
        $stmt = $this->db->query("SELECT rolle_id, berechtigung_id FROM rollen_berechtigungen");
        return $stmt->fetchAll();
    }

    /** Gibt den Rang einer Rolle zurück, oder null wenn nicht gefunden. */
    public function findRangById(int $rolleId): ?int
    {
        $stmt = $this->db->prepare("SELECT rang FROM rollen WHERE id = :id");
        $stmt->execute(['id' => $rolleId]);
        $wert = $stmt->fetchColumn();
        return $wert === false ? null : (int)$wert;
    }

    /** Prüft ob eine Berechtigung anhand Name existiert und gibt ihren Namen anhand ID zurück. */
    public function findBerechtigungName(int $berechtigungId): ?string
    {
        $stmt = $this->db->prepare("SELECT name FROM berechtigungen WHERE id = :id");
        $stmt->execute(['id' => $berechtigungId]);
        $wert = $stmt->fetchColumn();
        return $wert === false ? null : $wert;
    }

    /** Gewährt oder entzieht eine Berechtigung für eine Rolle. */
    public function setzeBerechtigung(int $rolleId, int $berechtigungId, bool $gewaehrt): void
    {
        if ($gewaehrt) {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO rollen_berechtigungen (rolle_id, berechtigung_id) VALUES (:rid, :bid)
            ");
            $stmt->execute(['rid' => $rolleId, 'bid' => $berechtigungId]);
        } else {
            $stmt = $this->db->prepare("
                DELETE FROM rollen_berechtigungen WHERE rolle_id = :rid AND berechtigung_id = :bid
            ");
            $stmt->execute(['rid' => $rolleId, 'bid' => $berechtigungId]);
        }
    }
}
