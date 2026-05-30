<?php

require_once __DIR__ . '/../../core/Database.php';

class ArtikelRepository
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
                a.id,
                a.artikelnummer,
                a.name,
                a.artikeltyp,
                a.lauflaenge,
                a.aktiv,
                h.name AS hersteller,
                s.satz AS steuersatz
            FROM artikel a
            INNER JOIN hersteller h ON a.hersteller_id = h.id
            INNER JOIN steuerklassen s ON a.steuerklasse_id = s.id
        ");

        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT 
                a.id,
                a.artikelnummer,
                a.name,
                a.artikeltyp,
                a.lauflaenge,
                a.aktiv,
                a.beschreibung_lang,
                a.einheit,
                a.gewicht_artikel,
                a.grundpreis_bezug,
                a.inhalt_einheit,
                a.inhalt_menge,
                a.maschenprobe,
                a.nadelstaerke_von,
                a.nadelstaerke_bis,
                h.name AS hersteller,
                s.satz AS steuersatz
            FROM artikel a
            INNER JOIN hersteller h ON a.hersteller_id = h.id
            INNER JOIN steuerklassen s ON a.steuerklasse_id = s.id
            WHERE a.id = :id
        ");

        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
}
