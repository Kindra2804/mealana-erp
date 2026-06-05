<?php

require_once __DIR__ . '/../../core/Database.php';

class LagerRepository
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
                l.aktiv,
                l.erstellt_am,
                l.typ,
                lb.charge,
                lb.charge_status,
                lb.bestand,
                lb.mindestbestand
            FROM lager l
            INNER JOIN lagerbestand lb ON l.id = lb.lager_id
        ");

        return $stmt->fetchAll();
    }

    public function findBestandByArtikelVarianteId(int $varianteId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT 
                l.id,
                l.name,
                l.aktiv,
                l.erstellt_am,
                l.typ,
                lb.charge,
                lb.charge_status,
                lb.bestand,
                lb.mindestbestand,
                av.artikel_id AS ArtikelID,
                av.farbe_name AS Farbe
            FROM lager l
            INNER JOIN lagerbestand lb ON l.id = lb.lager_id
            Inner JOIN artikel_varianten av ON av.id = lb.artikel_varianten_id
            WHERE av.id = :variante_id
            ORDER BY lb.bestand DESC
        ");

        $stmt->execute(['variante_id' => $varianteId]);
        return $stmt->fetchAll();
    }

    public function findBestandByLager(int $lagerId): array
    {
        $stmt = $this->db->prepare("
        SELECT 
            l.id,
            l.name,
            l.aktiv,
            l.erstellt_am,
            l.typ,
            lb.charge,
            lb.charge_status,
            lb.bestand,
            lb.mindestbestand,
            av.artikel_id AS ArtikelID,
            av.farbe_name AS Farbe
            FROM lager l
            INNER JOIN lagerbestand lb ON l.id = lb.lager_id
            Inner JOIN artikel_varianten av ON av.id = lb.artikel_varianten_id
            WHERE l.id = :lager_id
            ORDER BY lb.bestand DESC
    ");

        $stmt->execute(['lager_id' => $lagerId]);
        return $stmt->fetchAll();
    }

    public function findChargenByVarianteId(int $varianteId): array
    {
        $stmt = $this->db->prepare("
        SELECT 
            l.id,
            l.name,
            l.aktiv,
            l.erstellt_am,
            l.typ,
            lb.charge,
            lb.charge_status,
            lb.bestand,
            lb.mindestbestand,
            av.artikel_id AS ArtikelID,
            av.farbe_name AS Farbe
            FROM lager l
            INNER JOIN lagerbestand lb ON l.id = lb.lager_id
            Inner JOIN artikel_varianten av ON av.id = lb.artikel_varianten_id
            WHERE av.id = :variante_id
            ORDER BY lb.bestand DESC
    ");

        $stmt->execute(['variante_id' => $varianteId]);
        return $stmt->fetchAll();
    }

    public function findNachzutragendeChargen(): array
    {
        $stmt = $this->db->query("
        SELECT 
            l.id,
            l.name,
            l.aktiv,
            l.erstellt_am,
            l.typ,
            lb.charge,
            lb.charge_status,
            lb.bestand,
            lb.mindestbestand,
            av.artikel_id AS ArtikelID,
            av.farbe_name AS Farbe
            FROM lager l
            INNER JOIN lagerbestand lb ON l.id = lb.lager_id
            Inner JOIN artikel_varianten av ON av.id = lb.artikel_varianten_id
            WHERE lb.charge_status = 'nachzutragen'
            ORDER BY lb.bestand DESC
    ");

        return $stmt->fetchAll();
    }

    public function findBewegungByVarianteId(int $varianteId): array
    {
        $stmt = $this->db->prepare("
        SELECT 
            l.id,
            l.name,
            lb.bewegungstyp,
            lb.menge,
            lb.bestand_vorher,
            lb.bestand_nachher,
            lb.referenz,
            lb.notiz,
            av.artikel_id AS ArtikelID,
            av.farbe_name AS Farbe
            FROM lager l
            INNER JOIN lager_bewegungen lb ON l.id = lb.lager_id
            Inner JOIN artikel_varianten av ON av.id = lb.artikel_varianten_id
            WHERE av.id = :variante_id
            ORDER BY lb.erstellt_am DESC
    ");

        $stmt->execute(['variante_id' => $varianteId]);
        return $stmt->fetchAll();
    }

    public function getBestand(?int $varianteId, ?int $artikelId, int $lagerId): float
    {
        $stmt = $this->db->prepare("
        SELECT bestand FROM lagerbestand
        WHERE (
            artikel_varianten_id = :variante_id
            OR artikel_id = :artikel_id
        )
        AND lager_id = :lager_id
        ");

        $stmt->execute(['variante_id' => $varianteId, 'artikel_id' => $artikelId, 'lager_id' => $lagerId]);

        $result = $stmt->fetch();
        return $result ? (float) $result['bestand'] : 0.0;
    }

    public function upsertBestand(array $data): bool
    {
        $stmt = $this->db->prepare("
        INSERT INTO lagerbestand (
            artikel_varianten_id, 
            artikel_id,
            lager_id, 
            charge, 
            charge_status, 
            bestand, 
            mindestbestand
        ) VALUES (
            :artikel_varianten_id, 
            :artikel_id,
            :lager_id, 
            :charge, 
            :charge_status, 
            :bestand, 
            :mindestbestand
        )
        ON DUPLICATE KEY UPDATE
            charge = VALUES(charge),
            charge_status = VALUES(charge_status),
            bestand = VALUES(bestand),
            mindestbestand = VALUES(mindestbestand)
        ");
        $stmt->execute($data);
        return $stmt->rowCount() > 0;
    }

    public function insertBewegung(array $data): int
    {
        $stmt = $this->db->prepare("
        INSERT INTO lager_bewegungen (
            artikel_varianten_id,
            artikel_id,
            lager_id,
            charge,
            bewegungstyp,
            menge,
            bestand_vorher,
            bestand_nachher,
            referenz,
            notiz
        ) VALUES (
            :artikel_varianten_id,
            :artikel_id,
            :lager_id,
            :charge,
            :bewegungstyp,
            :menge,
            :bestand_vorher,
            :bestand_nachher,
            :referenz,
            :notiz
        )
    ");

        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function findUebersicht(): array
    {
        $stmt = $this->db->query("
        -- Teil 1: Vater
            SELECT 
                'vater' AS zeilentyp, 
                a.id AS artikel_id, 
                a.artikelnummer AS vater_artikelnummer, 
                a.name AS artikel_name,
                NULL AS varianten_artikelnummer, 
                NULL AS farbe, 
                NULL AS lager_name,
                SUM(lb.bestand) AS bestand, 
                NULL AS charge, 
                NULL AS charge_status
            FROM artikel a
            LEFT JOIN artikel_varianten av ON av.artikel_id = a.id
            INNER JOIN lagerbestand lb ON lb.artikel_varianten_id = av.id
            where lb.bestand > 0
            GROUP BY a.id, a.artikelnummer, a.name

            UNION ALL

            -- Teil 2: Kind
            SELECT 
                'kind' AS zeilentyp, 
                av.artikel_id AS artikel_id, 
                NULL AS vater_artikelnummer, 
                a.name AS artikel_name,
                av.artikelnummer AS varianten_artikelnummer, 
                av.farbe_name AS farbe, 
                l.name AS lager_name,
                lb.bestand AS bestand, 
                lb.charge AS charge, 
                lb.charge_status AS charge_status
            FROM artikel_varianten av
            INNER JOIN lagerbestand lb ON lb.artikel_varianten_id = av.id
            inner JOIN artikel a ON a.id = av.artikel_id
            inner JOIN lager l ON l.id = lb.lager_id
            where lb.bestand > 0

            UNION ALL

            -- Teil 3: standalone Artikel
            SELECT 
                'standalone' AS zeilentyp,
                a.id AS artikel_id,
                a.artikelnummer AS vater_artikelnummer, 
                a.name AS artikel_name,
                NULL AS varianten_artikelnummer, 
                NULL AS farbe, 
                l.name AS lager_name,
                lb.bestand AS bestand, 
                lb.charge AS charge, 
                lb.charge_status AS charge_status
            FROM artikel a
            INNER JOIN lagerbestand lb ON lb.artikel_id = a.id
            inner JOIN lager l ON l.id = lb.lager_id
            where (
                a.ist_vater = 0 AND
                lb.bestand > 0
            )
            ORDER BY artikel_name, zeilentyp DESC, farbe
        ");

        return $stmt->fetchAll();
    }

    public function getChargePflicht(?int $varianteId, ?int $artikelId): bool
    {
        // Wenn Variante: JOIN über artikel_varianten → artikel
        if ($varianteId) {
            $stmt = $this->db->prepare("
            SELECT 
                a.charge_pflicht
            FROM artikel_varianten av
            INNER JOIN artikel a ON a.id = av.artikel_id
            WHERE av.id = :variante_id
        ");
            $stmt->execute(['variante_id' => $varianteId]);
            $result = $stmt->fetch();
        }
        // Wenn Standalone: direkt auf artikel schauen
        else {
            $stmt = $this->db->prepare("
            SELECT 
                a.charge_pflicht
            FROM artikel a
            WHERE a.id = :artikel_id
        ");
            $stmt->execute(['artikel_id' => $artikelId]);
            $result = $stmt->fetch();
        }

        return (bool) ($result['charge_pflicht'] ?? false);
    }
}
