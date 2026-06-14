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
                l.id, l.name, l.aktiv, l.erstellt_am, l.typ,
                lb.charge, lb.charge_status, lb.bestand, lb.mindestbestand
            FROM lager l
            INNER JOIN lagerbestand lb ON l.id = lb.lager_id
        ");
        return $stmt->fetchAll();
    }

    public function findBestandByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                l.id, l.name, l.aktiv, l.typ,
                lb.charge, lb.charge_status, lb.bestand, lb.mindestbestand,
                a.id AS ArtikelID
            FROM lager l
            INNER JOIN lagerbestand lb ON l.id = lb.lager_id
            INNER JOIN artikel a ON a.id = lb.artikel_id
            WHERE lb.artikel_id = :artikel_id
            ORDER BY lb.bestand DESC
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    public function findBestandByLager(int $lagerId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                l.id, l.name, l.aktiv, l.typ,
                lb.charge, lb.charge_status, lb.bestand, lb.mindestbestand,
                a.id AS ArtikelID
            FROM lager l
            INNER JOIN lagerbestand lb ON l.id = lb.lager_id
            INNER JOIN artikel a ON a.id = lb.artikel_id
            WHERE l.id = :lager_id
            ORDER BY lb.bestand DESC
        ");
        $stmt->execute(['lager_id' => $lagerId]);
        return $stmt->fetchAll();
    }

    public function findChargenByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                l.id, l.name, l.aktiv, l.typ,
                lb.charge, lb.charge_status, lb.bestand, lb.mindestbestand,
                a.id AS ArtikelID
            FROM lager l
            INNER JOIN lagerbestand lb ON l.id = lb.lager_id
            INNER JOIN artikel a ON a.id = lb.artikel_id
            WHERE lb.artikel_id = :artikel_id
            ORDER BY lb.bestand DESC
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    public function findNachzutragendeChargen(): array
    {
        $stmt = $this->db->query("
            SELECT
                lb.id,
                COALESCE(vater.name, a.name) AS artikel_name,
                COALESCE(vater.artikelnummer, a.artikelnummer) AS vater_nr,
                CASE WHEN a.vaterartikel_id IS NOT NULL THEN a.artikelnummer END AS variante_nr,
                l.name AS lager_name,
                lb.bestand,
                lb.artikel_id
            FROM lagerbestand lb
            INNER JOIN artikel a ON a.id = lb.artikel_id
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            INNER JOIN lager l ON l.id = lb.lager_id
            WHERE lb.charge_status = 'nachzutragen'
            AND a.charge_pflicht = 1
            ORDER BY artikel_name, a.name
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

    public function getBestand(int $artikelId, int $lagerId, ?string $charge = null): float
    {
        if ($charge !== null) {
            $stmt = $this->db->prepare("
                SELECT bestand FROM lagerbestand
                WHERE artikel_id = :artikel_id AND lager_id = :lager_id AND charge = :charge
            ");
            $stmt->execute(['artikel_id' => $artikelId, 'lager_id' => $lagerId, 'charge' => $charge]);
        } else {
            $stmt = $this->db->prepare("
                SELECT bestand FROM lagerbestand
                WHERE artikel_id = :artikel_id AND lager_id = :lager_id AND charge IS NULL
            ");
            $stmt->execute(['artikel_id' => $artikelId, 'lager_id' => $lagerId]);
        }
        $result = $stmt->fetch();
        return $result ? (float) $result['bestand'] : 0.0;
    }

    public function upsertBestand(array $data): bool
    {
        if ($data['charge'] !== null) {
            $stmt = $this->db->prepare("
                INSERT INTO lagerbestand (
                    artikel_id, lager_id, charge, charge_status, bestand, mindestbestand
                ) VALUES (
                    :artikel_id, :lager_id, :charge, :charge_status, :bestand, :mindestbestand
                )
                ON DUPLICATE KEY UPDATE
                    charge        = VALUES(charge),
                    charge_status = VALUES(charge_status),
                    bestand       = VALUES(bestand),
                    mindestbestand = VALUES(mindestbestand)
            ");
            $stmt->execute($data);
            return $stmt->rowCount() > 0;
        } else {
            $check = $this->db->prepare("
                SELECT id FROM lagerbestand
                WHERE artikel_id = :artikel_id AND lager_id = :lager_id AND charge IS NULL
            ");
            $check->execute(['artikel_id' => $data['artikel_id'], 'lager_id' => $data['lager_id']]);
            $existing = $check->fetch();

            if ($existing) {
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
                $stmt = $this->db->prepare("
                    INSERT INTO lagerbestand (
                        artikel_id, lager_id, charge, charge_status, bestand, mindestbestand
                    ) VALUES (
                        :artikel_id, :lager_id, :charge, :charge_status, :bestand, :mindestbestand
                    )
                ");
                $stmt->execute($data);
            }
            return true;
        }
    }

    public function deleteBestand(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM lagerbestand WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function updateBestandMenge(int $id, float $bestand): bool
    {
        $stmt = $this->db->prepare("UPDATE lagerbestand SET bestand = :bestand WHERE id = :id");
        $stmt->execute(['id' => $id, 'bestand' => $bestand]);
        return $stmt->rowCount() > 0;
    }

    public function insertBewegung(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO lager_bewegungen (
                artikel_id, lager_id, lieferant_id, ek_preis,
                charge, bewegungstyp, menge, bestand_vorher, bestand_nachher,
                referenz, notiz, benutzer_id
            ) VALUES (
                :artikel_id, :lager_id, :lieferant_id, :ek_preis,
                :charge, :bewegungstyp, :menge, :bestand_vorher, :bestand_nachher,
                :referenz, :notiz, :benutzer_id
            )
        ");
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function findUebersicht(): array
    {
        $stmt = $this->db->query("
            -- Vater-Artikel (haben Kinder, kein eigener Bestand)
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
            INNER JOIN artikel kind ON kind.vaterartikel_id = a.id
            INNER JOIN lagerbestand lb ON lb.artikel_id = kind.id
            WHERE lb.bestand > 0
            GROUP BY a.id, a.artikelnummer, a.name

            UNION ALL

            -- Kind-Artikel (je Charge/Lager eine Zeile)
            SELECT
                'kind' AS zeilentyp,
                a.vaterartikel_id AS artikel_id,
                NULL AS vater_artikelnummer,
                vater.name AS artikel_name,
                a.artikelnummer AS varianten_artikelnummer,
                a.name AS farbe,
                l.name AS lager_name,
                lb.bestand AS bestand,
                lb.charge AS charge,
                lb.charge_status AS charge_status
            FROM artikel a
            INNER JOIN lagerbestand lb ON lb.artikel_id = a.id
            INNER JOIN artikel vater ON vater.id = a.vaterartikel_id
            INNER JOIN lager l ON l.id = lb.lager_id
            WHERE lb.bestand > 0

            UNION ALL

            -- Standalone-Artikel (Kopf – Summe aller Chargen)
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
            WHERE a.vaterartikel_id IS NULL AND a.ist_vater = 0 AND lb.bestand > 0
            GROUP BY a.id, a.artikelnummer, a.name

            UNION ALL

            -- Standalone-Artikel (je Charge/Lager eine Zeile)
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
            WHERE a.vaterartikel_id IS NULL AND a.ist_vater = 0 AND lb.bestand > 0

            ORDER BY artikel_name, zeilentyp DESC, farbe
        ");
        return $stmt->fetchAll();
    }

    public function getChargePflicht(int $artikelId): bool
    {
        $stmt = $this->db->prepare("SELECT charge_pflicht FROM artikel WHERE id = :id");
        $stmt->execute(['id' => $artikelId]);
        $result = $stmt->fetch();
        return (bool) ($result['charge_pflicht'] ?? false);
    }

    public function findLagerbestandById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT id, artikel_id, lager_id, bestand FROM lagerbestand WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function findBestandChargeProLager(int $artikelId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            lb.id,
            lb.lager_id,
            l.name AS lager_name,
            lb.charge,
            lb.charge_status,
            lb.bestand,
            lb.mindestbestand
        FROM lagerbestand lb
        JOIN lager l ON l.id = lb.lager_id
        WHERE lb.artikel_id = :artikel_id
        ORDER BY l.name, lb.charge
        ");

        $stmt->execute(['artikel_id' => $artikelId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $lagerGruppen = [];  // startet leer

        foreach ($rows as $row) {
            $lid = $row['lager_id'];

            // Beim ersten Mal für dieses Lager: Grundstruktur anlegen
            if (!isset($lagerGruppen[$lid])) {
                $lagerGruppen[$lid] = [
                    'name'          => $row['lager_name'],
                    'gesamt'        => 0,
                    'mindestbestand' => $row['mindestbestand'],
                    'chargen'       => [],
                ];
            }

            // Jede Zeile: Bestand aufsummieren
            $lagerGruppen[$lid]['gesamt'] += $row['bestand'];

            // Nur wenn Charge vorhanden: anhängen
            if ($row['charge'] !== null) {
                $lagerGruppen[$lid]['chargen'][] = $row;
            }
        }

        return $lagerGruppen;
    }

    public function findAlleLager(): array
    {
        $stmt = $this->db->query("SELECT id, name FROM lager WHERE aktiv = 1 ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBewegungslogFuerArtikel(int $artikelId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            lb.artikel_id,
            lb.lager_id,
            l.name,
            lb.bewegungstyp,
            lb.menge,
            lb.bestand_vorher,
            lb.bestand_nachher,
            lb.charge,
            lb.referenz,
            lb.notiz,
            lb.erstellt_am,
            b.formularname,
            l.name AS lager_name
            FROM lager_bewegungen lb
            LEFT JOIN benutzer b ON b.id = lb.benutzer_id
            JOIN lager l ON l.id = lb.lager_id
            WHERE lb.artikel_id = :artikel_id
            ORDER BY lb.erstellt_am DESC
            LIMIT 10
        ");

        $stmt->execute(['artikel_id' => $artikelId]);

        $bewegungen = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $bewegungen;
    }
}
