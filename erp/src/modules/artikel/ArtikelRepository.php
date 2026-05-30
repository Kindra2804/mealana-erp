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

    public function findVariantenByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            id,
            artikelnummer,
            gtin,
            farbe_name,
            farbe_hex,
            bild_url,
            brutto_vk,
            aktiv
        FROM artikel_varianten
        WHERE artikel_id = :artikel_id
        AND aktiv = 1
        ORDER BY farbe_name ASC
    ");

        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    public function findByIdMitVarianten(int $id): array|false
    {
        $artikel = $this->findById($id);

        if ($artikel === false) {
            return false;
        }

        $artikel['varianten'] = $this->findVariantenByArtikelId($id);

        return $artikel;
    }

    public function findByIdMitPreisen(int $id): array|false
    {
        $artikel = $this->findByIdMitVarianten($id);

        if ($artikel === false) {
            return false;
        }

        $stmt = $this->db->prepare("
        SELECT
            k.name AS kundengruppe,
            k.rabatt_prozent,
            p.brutto_vk,
            p.netto_vk,
            p.gueltig_ab,
            p.gueltig_bis
        FROM artikel_preise p
        LEFT JOIN kundengruppen k ON p.kundengruppen_id = k.id
        WHERE p.artikel_id = :artikel_id
        ORDER BY k.name ASC
    ");

        $stmt->execute(['artikel_id' => $id]);
        $artikel['preise'] = $stmt->fetchAll();

        return $artikel;
    }
}
