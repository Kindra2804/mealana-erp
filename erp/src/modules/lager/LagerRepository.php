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
        -- Teil 1: Varianten-Zeilen
        SELECT lb.id, a.name AS artikel_name, a.artikelnummer AS vater_nr,
            av.artikelnummer AS variante_nr, av.farbe_name, l.name AS lager_name,
            lb.bestand, lb.artikel_varianten_id, NULL AS artikel_id
        FROM lagerbestand lb
        INNER JOIN artikel_varianten av ON av.id = lb.artikel_varianten_id
        INNER JOIN artikel a ON a.id = av.artikel_id
        INNER JOIN lager l ON l.id = lb.lager_id
        WHERE lb.charge_status = 'nachzutragen'
        AND a.charge_pflicht = 1

        UNION ALL

        -- Teil 2: Standalone-Zeilen
        SELECT lb.id, a.name AS artikel_name, a.artikelnummer AS vater_nr,
            NULL AS variante_nr, NULL AS farbe_name, l.name AS lager_name,
            lb.bestand, NULL AS artikel_varianten_id, lb.artikel_id
        FROM lagerbestand lb
        INNER JOIN artikel a ON a.id = lb.artikel_id
        INNER JOIN lager l ON l.id = lb.lager_id
        WHERE lb.charge_status = 'nachzutragen'
        AND a.charge_pflicht = 1

        ORDER BY artikel_name, farbe_name

    ");

        return $stmt->fetchAll();
    }

    public function updateCharge(int $lagerbestandId, string $charge): bool
    {
        $stmt = $this->db->prepare("
        UPDATE lagerbestand
        SET charge = :charge, charge_status = 'erfasst'
        WHERE id = :id
    ");
        $stmt->execute(['charge' => $charge, 'id' => $lagerbestandId]);
        return $stmt->rowCount() > 0;
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

    public function getBestand(?int $varianteId, ?int $artikelId, int $lagerId, ?string $charge = null): float
    {
        if ($charge !== null) {
            $stmt = $this->db->prepare("
            SELECT bestand FROM lagerbestand
            WHERE (artikel_varianten_id = :variante_id OR artikel_id = :artikel_id)
            AND lager_id = :lager_id
            AND charge = :charge
        ");
            $stmt->execute(['variante_id' => $varianteId, 'artikel_id' => $artikelId, 'lager_id' => $lagerId, 'charge' => $charge]);
        } else {
            $stmt = $this->db->prepare("
            SELECT bestand FROM lagerbestand
            WHERE (artikel_varianten_id = :variante_id OR artikel_id = :artikel_id)
            AND lager_id = :lager_id
            AND charge IS NULL
        ");
            $stmt->execute(['variante_id' => $varianteId, 'artikel_id' => $artikelId, 'lager_id' => $lagerId]);
        }
        $result = $stmt->fetch();
        return $result ? (float) $result['bestand'] : 0.0;
    }


    public function upsertBestand(array $data): bool
    {
        if ($data['charge'] !== null) {
            // Weg A: normaler Upsert
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
        } else {
            // Schritt 1: Existiert schon eine NULL-Zeile?
            $check = $this->db->prepare("
                SELECT id FROM lagerbestand
                WHERE (artikel_varianten_id = :avid OR artikel_id = :aid)
                AND lager_id = :lid
                AND charge IS NULL
            ");
            $check->execute([
                'avid' => $data['artikel_varianten_id'],
                'aid'  => $data['artikel_id'],
                'lid'  => $data['lager_id'],
            ]);
            $existing = $check->fetch();

            if ($existing) {
                // Schritt 2a: UPDATE per id
                $stmt = $this->db->prepare("
                    UPDATE lagerbestand SET
                        bestand        = :bestand,
                        charge_status  = :charge_status,
                        mindestbestand = :mindestbestand
                    WHERE id = :id
                ");
                $stmt->execute([
                    'bestand'        => $data['bestand'],
                    'charge_status'  => $data['charge_status'],
                    'mindestbestand' => $data['mindestbestand'],
                    'id'             => $existing['id'],
                ]);
            } else {
                // Schritt 2b: frischer INSERT (kein ON DUPLICATE KEY nötig)
                $stmt = $this->db->prepare("
                INSERT INTO lagerbestand (
                    artikel_varianten_id, artikel_id, lager_id,
                    charge, charge_status, bestand, mindestbestand
                ) VALUES (
                    :artikel_varianten_id, :artikel_id, :lager_id,
                    :charge, :charge_status, :bestand, :mindestbestand
                )
            ");
                $stmt->execute($data);
            }
            return true;
        }
    }

    public function deleteBestand(int $id): bool
    {
        $stmt = $this->db->prepare("
        DELETE FROM lagerbestand WHERE id = :id
        ");

        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function updateBestandMenge(int $id, float $bestand): bool
    {
        $stmt = $this->db->prepare("
        UPDATE lagerbestand SET
            bestand= :bestand
        WHERE id = :id
        ");

        $stmt->execute(['id' => $id, 'bestand' => $bestand]);
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

            -- Teil 3a: standalone Kopf (Summe aller Chargen)
            SELECT 
                'standalone' AS zeilentyp,
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
            INNER JOIN lagerbestand lb ON lb.artikel_id = a.id
            WHERE a.ist_vater = 0 AND lb.bestand > 0
            GROUP BY a.id, a.artikelnummer, a.name

            UNION ALL

            -- Teil 3b: standalone Kind (eine Zeile pro Charge)
            SELECT 
                'standalone_kind' AS zeilentyp,
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
            INNER JOIN lager l ON l.id = lb.lager_id
            WHERE a.ist_vater = 0 AND lb.bestand > 0

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

    public function findLagerbestandById(int $id): array|false
    {
        $stmt = $this->db->prepare("
        SELECT id, artikel_varianten_id, artikel_id, lager_id, bestand
        FROM lagerbestand
        WHERE id = :id
    ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
}
