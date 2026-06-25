<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * BestellungRepository – CRUD für Einkaufsbestellungen und Positionen
 *
 * Bestellungen laufen von "offen" → "teilgeliefert" → "erledigt" (oder "storniert").
 * Jede Bestellung hat einen Lieferanten und beliebig viele Positionen.
 *
 * Bestellnummer-Format: "BE-2026-0001" (generiert via BestellungService::bestellnummer()).
 * Positionen können als "gestrichen" markiert werden (gestrichen = 1) wenn
 * Restmengen nicht mehr erwartet werden (DROPS-Modell: liefern was da ist).
 *
 * findReserviertNichtLagerndFuerLieferant() zeigt offene Reservierungen bei
 * denen der Bestand kleiner als die reservierte Menge ist — Rückstandsliste
 * für die Bestellvorschlag-Ansicht.
 */
class BestellungRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gibt alle Bestellungen zurück, optional nach Status und Lieferant gefiltert.
     * Enthält aggregierte Summen: Gesamtbetrag (EK), Gesamtmenge bestellt/eingegangen.
     * Gestrichene Positionen werden in den Aggregierungen ausgeschlossen.
     *
     * @param string $status      "" für alle, sonst "offen" | "teilgeliefert" | "erledigt" | "storniert"
     * @param int    $lieferantId 0 für alle Lieferanten
     */
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

    /**
     * Gibt eine einzelne Bestellung mit Lieferanten-Name zurück.
     * Gibt false zurück wenn nicht gefunden.
     */
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

    /** Filtert Bestellungen nach einem bestimmten Lieferanten. */
    public function findNachLieferant(int $lieferantId): array
    {
        return $this->findAll('', $lieferantId);
    }

    /** Legt eine neue Bestellung an und gibt die neue ID zurück. */
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

    /** Fügt eine Position zu einer Bestellung hinzu und gibt die neue ID zurück. */
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

    /** Aktualisiert Kopfdaten einer Bestellung (kein Status, keine Rechnungsfelder). */
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

    /** Setzt den Status einer Bestellung (offen/teilgeliefert/erledigt/storniert). */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE bestellungen SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** Speichert Rechnungs- und Lieferscheindaten zu einer erledigten Bestellung. */
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

    /**
     * Gibt alle Positionen einer Bestellung zurück mit Artikel-Details.
     * Enthält: COALESCE Vater/Kind-Name, Varianten-Name, Lieferzeit aus artikel_lieferanten,
     * und das Hauptbild per Subquery (für Packplatz-Ansicht, Fehlerreduktion beim Scan).
     * Muss die Bestellungs-ID zweimal übergeben werden (:bestellung_id und :best_id2),
     * weil derselbe Parameter nicht mehrfach in PDO-Subqueries genutzt werden kann.
     */
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

    /** Löscht eine Bestellposition dauerhaft. */
    public function deletePosition(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM bestellung_positionen WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Rückstandsliste: Artikel bei diesem Lieferanten, für die es offene Reservierungen
     * gibt aber der Lagerbestand nicht ausreicht.
     *
     * Formel: SUM(reservierungen.menge) > SUM(lagerbestand.bestand) — via HAVING-Klausel.
     * Gibt VPE-Menge und EK-Preis aus artikel_lieferanten mit zurück für
     * den direkten Übernahme-Button in eine neue Bestellung.
     */
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

    /**
     * Bestellvorschläge: Alle aktiven Nicht-Vater-Artikel die unter Meldebestand
     * ODER in Unterdeckung (verfügbar < 0) sind, mit Standard-Lieferant-Infos.
     */
    public function findBestellvorschlaege(): array
    {
        $stmt = $this->db->query("
            SELECT
                a.id,
                a.name             AS artikel_name,
                a.artikelnummer,
                a.meldebestand,
                COALESCE(
                    (SELECT SUM(lb.bestand) FROM lagerbestand lb WHERE lb.artikel_id = a.id),
                    0
                )                  AS gesamtbestand,
                COALESCE(
                    (SELECT SUM(r.menge) FROM reservierungen r WHERE r.artikel_id = a.id AND r.status = 'offen'),
                    0
                )                  AS reserviert,
                al.lieferant_id    AS std_lieferant_id,
                l.name             AS std_lieferant_name,
                COALESCE(al.vpe_menge, 1) AS vpe_menge,
                al.netto_ek
            FROM artikel a
            LEFT JOIN artikel_lieferanten al ON al.artikel_id = a.id AND al.standard_lieferant = 1 AND al.aktiv = 1
            LEFT JOIN lieferanten l ON l.id = al.lieferant_id
            WHERE a.aktiv = 1 AND a.ist_vater = 0
            HAVING
                (a.meldebestand IS NOT NULL AND gesamtbestand <= a.meldebestand)
                OR (gesamtbestand - reserviert) < 0
            ORDER BY a.name
        ");
        return $stmt->fetchAll();
    }

    /** Gibt alle aktiven Lieferanten für das Bestellformular-Dropdown zurück. */
    public function findAlleLieferanten(): array
    {
        $stmt = $this->db->query("SELECT id, name FROM lieferanten WHERE aktiv = 1 ORDER BY name");
        return $stmt->fetchAll();
    }

    /**
     * Gibt Artikel zurück, die einem Lieferanten zugeordnet sind (für Positionseingabe).
     * Limit 20 für schnelle Typeahead-Anzeige. Suche in Artikel- und Vater-Name/Nummer.
     * Wenn $suche leer: alle Artikel des Lieferanten (bis Limit 20).
     */
    public function findArtikelFuerLieferant(int $lieferantId, string $suche = ''): array
    {
        $where = "WHERE al.lieferant_id = :lieferant_id AND al.aktiv = 1 AND a.aktiv = 1";

        if ($suche != '') {
            $where = "WHERE al.lieferant_id = :lieferant_id AND al.aktiv = 1 AND a.aktiv = 1
                        AND (a.name LIKE :suche OR vater.name LIKE :suche
                        OR a.artikelnummer LIKE :suche OR vater.artikelnummer LIKE :suche)
        ";
        };

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
            $where
            ORDER BY name, a.name
            LIMIT 20
        ");
        $stmt->execute(['lieferant_id' => $lieferantId, 'suche' => '%' . $suche . '%']);
        return $stmt->fetchAll();
    }

    /**
     * Sucht über alle aktiven Artikel (nicht nur Lieferanten-zugeordnete).
     * Für "?alle=1" Mode im AJAX-Endpunkt — wenn Artikel nachträglich zu einer Bestellung
     * hinzugefügt werden sollen, auch ohne bestehende Lieferantenzuordnung.
     * ist_vater = 0 ausschließen: Vater-Artikel selbst sind keine Bestelleinheit.
     */
    public function findAlleArtikelFuerSuche(string $suche): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.id,
                COALESCE(vater.name, a.name)                  AS name,
                COALESCE(vater.artikelnummer, a.artikelnummer) AS artikelnummer,
                CASE WHEN a.vaterartikel_id IS NOT NULL THEN a.name END AS variante_name,
                NULL AS netto_ek,
                1    AS vpe_menge,
                NULL AS lieferzeit_tage
            FROM artikel a
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            WHERE a.aktiv = 1
              AND (a.ist_vater = 0 OR a.ist_vater IS NULL)
              AND (a.name LIKE :suche OR vater.name LIKE :suche
                OR a.artikelnummer LIKE :suche OR vater.artikelnummer LIKE :suche)
            ORDER BY COALESCE(vater.name, a.name), a.name
            LIMIT 20
        ");
        $stmt->execute(['suche' => '%' . $suche . '%']);
        return $stmt->fetchAll();
    }
}
