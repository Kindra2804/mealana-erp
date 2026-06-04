<?php

require_once __DIR__ . '/../../core/Database.php';

class ArtikelRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(bool $mitInaktiven = false): array
    {
        $where = $mitInaktiven ? '' : 'WHERE a.aktiv = 1';
        $stmt = $this->db->query("
            SELECT 
                a.id,
                a.artikelnummer,
                a.name,
                a.artikeltyp,
                a.aktiv,
                h.name AS hersteller,
                s.satz AS steuersatz,
                COALESCE(SUM(lb.bestand), 0) AS gesamtbestand
            FROM artikel a
            LEFT JOIN hersteller h ON a.hersteller_id = h.id
            LEFT JOIN steuerklassen s ON a.steuerklasse_id = s.id
            LEFT JOIN artikel_varianten av ON av.artikel_id = a.id
            LEFT JOIN lagerbestand lb ON lb.artikel_varianten_id = av.id
            $where
            GROUP BY a.id
        ");

        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT 
                a.hersteller_id,
                a.steuerklasse_id,
                a.grundpreis_bezugsmenge,
                a.grundpreis_anzeigen,
                a.gewicht_versand,
                a.herkunftsland,
                a.taric_code,
                a.varianten_darstellung,
                a.beschreibung_kurz,
                a.id,
                a.artikelnummer,
                a.name,
                a.artikeltyp,
                a.aktiv,
                a.beschreibung_lang,
                a.einheit,
                a.gewicht_artikel,
                a.inhalt_einheit,
                a.inhalt_menge,
                h.name AS hersteller,
                s.satz AS steuersatz
            FROM artikel a
            LEFT JOIN hersteller h ON a.hersteller_id = h.id
            LEFT JOIN steuerklassen s ON a.steuerklasse_id = s.id
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

    public function findByArtikelnummer(string $artikelnummer, ?int $excludeId = null): array|false
    {
        $sql = "SELECT id FROM artikel WHERE artikelnummer = :artikelnummer";
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
        }
        $stmt = $this->db->prepare($sql);
        $params = ['artikelnummer' => $artikelnummer];
        if ($excludeId) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function insert(array $data): int
    {

        $stmt = $this->db->prepare("
        INSERT INTO artikel (
            artikelnummer,
            hersteller_id,
            steuerklasse_id,
            artikeltyp,
            name,
            beschreibung_kurz,
            beschreibung_lang,
            einheit,
            inhalt_menge,
            inhalt_einheit,
            gewicht_artikel,
            gewicht_versand,
            herkunftsland,
            taric_code,
            varianten_darstellung,
            grundpreis_bezugsmenge,
            grundpreis_anzeigen,
            aktiv
        ) VALUES (
            :artikelnummer,
            :hersteller_id,
            :steuerklasse_id,
            :artikeltyp,
            :name,
            :beschreibung_kurz,
            :beschreibung_lang,
            :einheit,
            :inhalt_menge,
            :inhalt_einheit,
            :gewicht_artikel,
            :gewicht_versand,
            :herkunftsland,
            :taric_code,
            :varianten_darstellung,
            :grundpreis_bezugsmenge,
            :grundpreis_anzeigen,
            :aktiv
        )
    ");

        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(array $data): bool
    {

        $stmt = $this->db->prepare("
        UPDATE artikel SET
            artikelnummer = :artikelnummer,
            hersteller_id = :hersteller_id,
            steuerklasse_id = :steuerklasse_id,
            artikeltyp = :artikeltyp,
            name = :name,
            beschreibung_kurz = :beschreibung_kurz,
            beschreibung_lang = :beschreibung_lang,
            einheit = :einheit,
            inhalt_menge = :inhalt_menge,
            inhalt_einheit = :inhalt_einheit,
            gewicht_artikel = :gewicht_artikel,
            gewicht_versand = :gewicht_versand,
            herkunftsland = :herkunftsland,
            taric_code = :taric_code,
            varianten_darstellung = :varianten_darstellung,
            grundpreis_bezugsmenge = :grundpreis_bezugsmenge,
            grundpreis_anzeigen = :grundpreis_anzeigen,
            aktiv = :aktiv
        WHERE id = :id
    ");

        $stmt->execute($data);
        return $stmt->rowCount() > 0;
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->db->prepare("
        UPDATE artikel SET aktiv = 0 WHERE id = :id
    ");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function insertPreis(int $artikelId, float $bruttoVk, float $nettoVk, int $kundengruppenId = 1): bool
    {
        $stmt = $this->db->prepare("
        INSERT INTO artikel_preise (
            artikel_id,
            kundengruppen_id,
            brutto_vk,
            netto_vk
        ) VALUES (
            :artikel_id,
            :kundengruppen_id,
            :brutto_vk,
            :netto_vk
        )
    ");

        return $stmt->execute([
            'artikel_id'       => $artikelId,
            'kundengruppen_id' => $kundengruppenId,
            'brutto_vk'        => $bruttoVk,
            'netto_vk'         => $nettoVk
        ]);
    }

    public function updatePreis(int $artikelId, float $bruttoVk, float $nettoVk, int $kundengruppenId = 1): bool
    {
        // Erst prüfen ob Preis bereits existiert
        $stmt = $this->db->prepare("
        SELECT id FROM artikel_preise 
        WHERE artikel_id = :artikel_id 
        AND kundengruppen_id = :kundengruppen_id
    ");
        $stmt->execute(['artikel_id' => $artikelId, 'kundengruppen_id' => $kundengruppenId]);

        if ($stmt->fetch()) {
            // Update
            $stmt = $this->db->prepare("
            UPDATE artikel_preise SET
                brutto_vk = :brutto_vk,
                netto_vk = :netto_vk
            WHERE artikel_id = :artikel_id
            AND kundengruppen_id = :kundengruppen_id
        ");
        } else {
            // Insert
            $stmt = $this->db->prepare("
            INSERT INTO artikel_preise 
                (artikel_id, kundengruppen_id, brutto_vk, netto_vk)
            VALUES 
                (:artikel_id, :kundengruppen_id, :brutto_vk, :netto_vk)
        ");
        }

        return $stmt->execute([
            'artikel_id'       => $artikelId,
            'kundengruppen_id' => $kundengruppenId,
            'brutto_vk'        => $bruttoVk,
            'netto_vk'         => $nettoVk
        ]);
    }

    public function insertVariante(array $data): int
    {

        $stmt = $this->db->prepare("
        INSERT INTO artikel_varianten (
            artikel_id,
            artikelnummer,
            gtin,
            farbe_name,
            farbe_hex,
            bild_url,
            brutto_vk,
            aktiv
        ) VALUES (
            :artikel_id,
            :artikelnummer,
            :gtin,
            :farbe_name,
            :farbe_hex,
            :bild_url,
            :brutto_vk,
            :aktiv
        )
    ");

        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function findVarianteById(int $id): array|false
    {
        $stmt = $this->db->prepare("
        SELECT id, artikel_id, artikelnummer, gtin, 
               farbe_name, farbe_hex, bild_url, brutto_vk, aktiv
        FROM artikel_varianten
        WHERE id = :id
    ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function updateVariante(array $data): bool
    {
        unset($data['artikel_id']); // ← nicht im UPDATE SQL!

        $stmt = $this->db->prepare("
        UPDATE artikel_varianten SET
            artikelnummer = :artikelnummer,
            gtin          = :gtin,
            farbe_name    = :farbe_name,
            farbe_hex     = :farbe_hex,
            bild_url      = :bild_url,
            brutto_vk     = :brutto_vk,
            aktiv         = :aktiv
        WHERE id = :id
    ");
        $stmt->execute($data);

        return $stmt->rowCount() > 0;
    }

    public function search(string $q): array
    {
        $stmt = $this->db->prepare("
        SELECT 
            a.id, a.artikelnummer, a.name, 
            a.artikeltyp, a.aktiv,
            h.name AS hersteller
        FROM artikel a
        LEFT JOIN hersteller h ON a.hersteller_id = h.id
        WHERE a.aktiv = 1
        AND (
            a.artikelnummer LIKE :q
            OR a.name LIKE :q
            OR h.name LIKE :q
        )
        ORDER BY a.artikelnummer ASC
        LIMIT 50
    ");

        $stmt->execute(['q' => '%' . $q . '%']);
        return $stmt->fetchAll();
    }
}
