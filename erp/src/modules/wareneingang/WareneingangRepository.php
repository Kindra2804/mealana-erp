<?php

require_once __DIR__ . '/../../core/Database.php';

class WareneingangRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findOffene(): array
    {
        $stmt = $this->db->query("
            SELECT
                b.id,
                b.status,
                b.bestelldatum,
                b.erwartet_am,
                l.name                                                                          AS lieferant_name,
                COUNT(bp.id)                                                                    AS anzahl_positionen,
                SUM(CASE WHEN bp.menge_eingegangen >= bp.menge_bestellt THEN 1 ELSE 0 END)     AS positionen_erledigt
            FROM bestellungen b
            JOIN  lieferanten l ON l.id = b.lieferant_id
            LEFT JOIN bestellung_positionen bp ON bp.bestellung_id = b.id AND bp.gestrichen = 0
            WHERE b.status IN ('offen', 'teilgeliefert')
            GROUP BY b.id, b.status, b.bestelldatum, b.erwartet_am, l.name
            ORDER BY b.bestelldatum ASC
        ");
        return $stmt->fetchAll();
    }

    public function findArtikelByEan(string $ean): array|false
    {
        $stmt = $this->db->prepare("
            SELECT
                a.id,
                a.artikelnummer,
                a.charge_pflicht,
                a.vaterartikel_id,
                COALESCE(vater.name, a.name)   AS anzeige_name,
                a.name                         AS variante_name,
                (SELECT dateiname
                 FROM artikel_bilder
                 WHERE artikel_id = COALESCE(a.vaterartikel_id, a.id) AND position = 0
                 LIMIT 1)                      AS hauptbild
            FROM artikel_codes ac
            JOIN  artikel a ON a.id = ac.artikel_id
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            WHERE ac.code = :ean AND ac.typ IN ('GTIN13','GTIN8')
            LIMIT 1
        ");
        $stmt->execute(['ean' => $ean]);
        return $stmt->fetch();
    }

    public function findBestellungenFuerArtikel(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                b.id,
                b.bestelldatum,
                b.status,
                l.name                                         AS lieferant_name,
                bp.id                                          AS position_id,
                bp.menge_bestellt,
                bp.menge_eingegangen,
                (bp.menge_bestellt - bp.menge_eingegangen)     AS menge_offen
            FROM bestellungen b
            JOIN  lieferanten l ON l.id = b.lieferant_id
            JOIN  bestellung_positionen bp
                ON bp.bestellung_id = b.id AND bp.artikel_id = :artikel_id
            WHERE b.status IN ('offen', 'teilgeliefert')
              AND bp.gestrichen = 0
              AND bp.menge_eingegangen < bp.menge_bestellt
            ORDER BY b.bestelldatum ASC
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    public function findPosition(int $positionId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT bp.*, b.lieferant_id, b.id AS bestellung_id, b.bestelldatum
            FROM bestellung_positionen bp
            JOIN bestellungen b ON b.id = bp.bestellung_id
            WHERE bp.id = :id
        ");
        $stmt->execute(['id' => $positionId]);
        return $stmt->fetch();
    }

    public function findPositionenMitArtikel(int $bestellungId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                bp.*,
                COALESCE(vater.name, a.name)                     AS artikel_name,
                COALESCE(vater.artikelnummer, a.artikelnummer)    AS artikel_nr,
                CASE WHEN a.vaterartikel_id IS NOT NULL THEN a.name END AS variante_name,
                a.charge_pflicht,
                (SELECT dateiname
                 FROM artikel_bilder
                 WHERE artikel_id = COALESCE(a.vaterartikel_id, a.id) AND position = 0
                 LIMIT 1)                                         AS hauptbild
            FROM bestellung_positionen bp
            JOIN  artikel a ON a.id = bp.artikel_id
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            WHERE bp.bestellung_id = :bestellung_id
            ORDER BY bp.id
        ");
        $stmt->execute(['bestellung_id' => $bestellungId]);
        return $stmt->fetchAll();
    }

    public function updatePositionEingegangen(int $positionId, float $zusatzMenge): bool
    {
        $stmt = $this->db->prepare("
            UPDATE bestellung_positionen
            SET menge_eingegangen = menge_eingegangen + :menge
            WHERE id = :id
        ");
        $stmt->execute(['menge' => $zusatzMenge, 'id' => $positionId]);
        return $stmt->rowCount() > 0;
    }

    public function insertEingang(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO bestellung_eingaenge (
                position_id, bewegung_id, menge, charge, lager_id, benutzer_id
            ) VALUES (
                :position_id, :bewegung_id, :menge, :charge, :lager_id, :benutzer_id
            )
        ");
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function pruefeBestellungKomplett(int $bestellungId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS offen
            FROM bestellung_positionen
            WHERE bestellung_id = :id
              AND gestrichen = 0
              AND menge_eingegangen < menge_bestellt
        ");
        $stmt->execute(['id' => $bestellungId]);
        return (int)$stmt->fetch()['offen'] === 0;
    }

    public function updateBestellungStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare("UPDATE bestellungen SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function streicheRestPositionen(int $bestellungId, ?string $gutschriftNotiz, ?float $gutschriftBetrag): void
    {
        $stmt = $this->db->prepare("
            UPDATE bestellung_positionen
            SET gestrichen = 1
            WHERE bestellung_id = :id AND menge_eingegangen < menge_bestellt
        ");
        $stmt->execute(['id' => $bestellungId]);

        if ($gutschriftNotiz || $gutschriftBetrag) {
            $stmt = $this->db->prepare("
                UPDATE bestellungen SET gutschrift_betrag = :betrag, gutschrift_notiz = :notiz WHERE id = :id
            ");
            $stmt->execute(['betrag' => $gutschriftBetrag, 'notiz' => $gutschriftNotiz, 'id' => $bestellungId]);
        }
    }

    public function findChargenFuerArtikel(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT charge
            FROM lagerbestand
            WHERE artikel_id = :artikel_id AND charge IS NOT NULL AND bestand > 0
            ORDER BY charge
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function findAlleLager(): array
    {
        $stmt = $this->db->query("SELECT id, name FROM lager WHERE aktiv = 1 ORDER BY id");
        return $stmt->fetchAll();
    }
}
