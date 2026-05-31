<?php

require_once __DIR__ . '/../../core/Database.php';

class MerkmalRepository
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
                m.id,
                m.name,
                m.einheit,
                m.aktiv,
                m.datentyp,
                m.erstellt_am,
                m.filterbar,
                g.name AS Merkmalgruppenname
            FROM merkmale m
            INNER JOIN merkmal_gruppen g ON m.merkmal_gruppen_id = g.id
        ");

        return $stmt->fetchAll();
    }

    public function findMerkmaleByGroupId(int $groupId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT 
                m.id,
                m.name,
                m.einheit,
                m.aktiv,
                m.datentyp,
                m.erstellt_am,
                m.filterbar,
                g.id AS merkmal_gruppen_id,
                g.name AS Merkmalgruppenname
            FROM merkmale m
            INNER JOIN merkmal_gruppen g ON m.merkmal_gruppen_id = g.id
            WHERE m.merkmal_gruppen_id = :group_id
            AND m.aktiv = 1
            ORDER BY m.name ASC
        ");

        $stmt->execute(['group_id' => $groupId]);
        return $stmt->fetchAll();
    }

    public function findMerkmaleByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
        SELECT 
            a.id AS ArtikelID,
            a.name AS Artikelname,
            m.einheit,
            m.aktiv,
            m.datentyp,
            m.erstellt_am,
            m.filterbar,
            g.id AS merkmal_gruppen_id,
            g.name AS Merkmalgruppenname,
            am.wert_text,
            am.wert_zahl,
            am.wert_bool
            FROM merkmale m
            INNER JOIN merkmal_gruppen g ON m.merkmal_gruppen_id = g.id
            INNER JOIN artikel_merkmale am ON am.merkmal_id = m.id
            INNER JOIN artikel a ON a.id = am.artikel_id
            WHERE a.id = :artikel_id
            AND m.aktiv = 1
            ORDER BY m.name ASC
    ");

        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    public function findFilterbareByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
        SELECT 
            m.name,
            m.einheit,
            m.datentyp,
            g.name AS gruppenname,
            am.wert_text,
            am.wert_zahl,
            am.wert_bool
        FROM artikel_merkmale am
        INNER JOIN merkmale m ON am.merkmal_id = m.id
        INNER JOIN merkmal_gruppen g ON m.merkmal_gruppen_id = g.id
        WHERE am.artikel_id = :artikel_id
        AND m.filterbar = 1
        ORDER BY m.name ASC
    ");

        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }
}
