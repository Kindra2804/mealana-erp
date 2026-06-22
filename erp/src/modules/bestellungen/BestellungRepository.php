<?php

require_once __DIR__ . '/../../core/Database.php';

class BestellungRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(string $status = '', int $lieferantId = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($status !== '') {
            $where[] = 'b.status = :status';
            $params['status'] = $status;
        }
        if ($lieferantId > 0) {
            $where[] = 'b.lieferant_id = :lieferant_id';
            $params['lieferant_id'] = $lieferantId;
        }

        $stmt = $this->db->prepare("
            SELECT
                b.id,
                b.status,
                b.bestelldatum,
                b.erwartet_am,
                b.lieferzeit_text,
                b.ab_nummer,
                b.ls_nummer,
                b.rechnung_nummer,
                b.rechnung_betrag,
                b.notiz,
                l.name AS lieferant_name,
                COUNT(bp.id)                                                   AS anzahl_positionen,
                SUM(bp.menge_bestellt * COALESCE(bp.ek_preis, 0))              AS gesamt_ek,
                SUM(bp.menge_eingegangen)                                       AS gesamt_eingegangen,
                SUM(bp.menge_bestellt)                                          AS gesamt_bestellt
            FROM bestellungen b
            JOIN  lieferanten l ON l.id = b.lieferant_id
            LEFT JOIN bestellung_positionen bp ON bp.bestellung_id = b.id AND bp.gestrichen = 0
            WHERE " . implode(' AND ', $where) . "
            GROUP BY b.id, b.status, b.bestelldatum, b.erwartet_am, b.lieferzeit_text,
                     b.ab_nummer, b.ls_nummer, b.rechnung_nummer, b.rechnung_betrag, b.notiz,
                     l.name
            ORDER BY b.bestelldatum DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT b.*, l.name AS lieferant_name
            FROM bestellungen b
            JOIN lieferanten l ON l.id = b.lieferant_id
            WHERE b.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function findNachLieferant(int $lieferantId): array
    {
        return $this->findAll('', $lieferantId);
    }

    public function insert(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO bestellungen (
                lieferant_id, status, bestelldatum, erwartet_am, lieferzeit_text,
                zahlungsart, ab_nummer, notiz, benutzer_id
            ) VALUES (
                :lieferant_id, :status, :bestelldatum, :erwartet_am, :lieferzeit_text,
                :zahlungsart, :ab_nummer, :notiz, :benutzer_id
            )
        ");
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function insertPosition(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO bestellung_positionen (
                bestellung_id, artikel_id, menge_bestellt, ek_preis, lieferzeit_text
            ) VALUES (
                :bestellung_id, :artikel_id, :menge_bestellt, :ek_preis, :lieferzeit_text
            )
        ");
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function update(array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE bestellungen SET
                lieferant_id    = :lieferant_id,
                bestelldatum    = :bestelldatum,
                erwartet_am     = :erwartet_am,
                lieferzeit_text = :lieferzeit_text,
                zahlungsart     = :zahlungsart,
                ab_nummer       = :ab_nummer,
                notiz           = :notiz
            WHERE id = :id
        ");
        $stmt->execute($data);
        return $stmt->rowCount() > 0;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE bestellungen SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function updateRechnung(array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE bestellungen SET
                ls_nummer       = :ls_nummer,
                rechnung_nummer = :rechnung_nummer,
                rechnung_betrag = :rechnung_betrag,
                rechnung_datum  = :rechnung_datum
            WHERE id = :id
        ");
        $stmt->execute($data);
        return $stmt->rowCount() > 0;
    }

    public function findPositionen(int $bestellungId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                bp.*,
                COALESCE(vater.name, a.name)            AS artikel_name,
                COALESCE(vater.artikelnummer, a.artikelnummer) AS artikel_nr,
                CASE WHEN a.vaterartikel_id IS NOT NULL THEN a.name END AS variante_name,
                al.vpe_menge,
                al.lieferzeit_tage,
                (SELECT dateiname
                 FROM artikel_bilder
                 WHERE artikel_id = COALESCE(a.vaterartikel_id, a.id) AND position = 0
                 LIMIT 1)                               AS hauptbild
            FROM bestellung_positionen bp
            JOIN  artikel a    ON a.id    = bp.artikel_id
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            LEFT JOIN artikel_lieferanten al
                ON al.artikel_id  = bp.artikel_id
               AND al.lieferant_id = (SELECT lieferant_id FROM bestellungen WHERE id = :best_id2)
               AND al.aktiv = 1
            WHERE bp.bestellung_id = :bestellung_id
            ORDER BY bp.id
        ");
        $stmt->execute(['bestellung_id' => $bestellungId, 'best_id2' => $bestellungId]);
        return $stmt->fetchAll();
    }

    public function deletePosition(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM bestellung_positionen WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function findReserviertNichtLagerndFuerLieferant(int $lieferantId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.id,
                a.name               AS artikel_name,
                a.artikelnummer,
                SUM(r.menge)         AS reserviert_gesamt,
                al.vpe_menge,
                al.netto_ek,
                COALESCE(
                    (SELECT SUM(lb.bestand) FROM lagerbestand lb WHERE lb.artikel_id = a.id),
                    0
                )                    AS bestand_gesamt
            FROM reservierungen r
            JOIN artikel a ON a.id = r.artikel_id
            JOIN artikel_lieferanten al
                ON al.artikel_id = a.id AND al.lieferant_id = :lieferant_id AND al.aktiv = 1
            WHERE r.status = 'offen'
            GROUP BY a.id, a.name, a.artikelnummer, al.vpe_menge, al.netto_ek
            HAVING bestand_gesamt < reserviert_gesamt
            ORDER BY a.name
        ");
        $stmt->execute(['lieferant_id' => $lieferantId]);
        return $stmt->fetchAll();
    }

    public function findAlleLieferanten(): array
    {
        $stmt = $this->db->query("SELECT id, name FROM lieferanten WHERE aktiv = 1 ORDER BY name");
        return $stmt->fetchAll();
    }

    public function findArtikelFuerLieferant(int $lieferantId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.id,
                COALESCE(vater.name, a.name)                     AS name,
                COALESCE(vater.artikelnummer, a.artikelnummer)    AS artikelnummer,
                CASE WHEN a.vaterartikel_id IS NOT NULL THEN a.name END AS variante_name,
                al.netto_ek,
                al.vpe_menge,
                al.lieferzeit_tage
            FROM artikel_lieferanten al
            JOIN artikel a ON a.id = al.artikel_id
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            WHERE al.lieferant_id = :lieferant_id AND al.aktiv = 1 AND a.aktiv = 1
            ORDER BY name, a.name
        ");
        $stmt->execute(['lieferant_id' => $lieferantId]);
        return $stmt->fetchAll();
    }
}
