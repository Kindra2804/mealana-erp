<?php

require_once __DIR__ . '/../../core/Database.php';

class LieferantenRepository
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
                l.id,
                l.name,
                l.land,
                l.website,
                l.email,
                l.telefon,
                l.aktiv,
                l.erstellt_am
            FROM lieferanten l
        ");

        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
        SELECT 
            l.id,
            l.name,
            l.land,
            l.website,
            l.email,
            l.telefon,
            l.aktiv,
            l.erstellt_am
            FROM lieferanten l
            WHERE l.id = :id
        ");

        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function findVertreterByLieferantId(int $lieferantId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            id,
            vorname,
            nachname,
            telefon,
            email,
            mobil,
            notizen,
            erstellt_am,
            geaendert_am
        FROM lieferanten_vertreter
        WHERE lieferant_id = :lieferant_id
        AND aktiv = 1
        ORDER BY nachname ASC
    ");

        $stmt->execute(['lieferant_id' => $lieferantId]);
        return $stmt->fetchAll();
    }

    public function findByIdMitVertretern(int $id): array|false
    {
        $lieferanten = $this->findById($id);

        if ($lieferanten === false) {
            return false;
        }

        $lieferanten['vertreter'] = $this->findVertreterByLieferantId($id);

        return $lieferanten;
    }
}
