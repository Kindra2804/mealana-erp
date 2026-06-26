<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * WareneingangRepository – Datenzugriff für den Wareneingangs-Workflow
 *
 * Wareneingang ist der Prozess: Lieferung einscannen → Mengen buchen →
 * Bestellposition als "eingegangen" markieren → Bestellung abschließen.
 *
 * Jeder Buchungsvorgang erzeugt:
 * 1. Eine Lager-Bewegung (lager_bewegungen via LagerRepository)
 * 2. Einen Eingang-Record (bestellung_eingaenge) der Position + Bewegung verknüpft
 * 3. Die menge_eingegangen der Position wird erhöht
 * 4. Der Bestellungsstatus wird geprüft und ggf. auf teilgeliefert/erledigt gesetzt
 *
 * EAN-Scan: findArtikelByEan() sucht in artikel_codes (typ GTIN13/GTIN8).
 * Wenn der Artikel keine offene Bestellung hat → Sammelliste (Session-Durchlauf).
 */
class WareneingangRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gibt alle offenen und teilgelieferten Bestellungen zurück.
     * Enthält Fortschrittsinfo: anzahl_positionen vs. positionen_erledigt.
     * Gestrichene Positionen werden aus dem Fortschritt ausgeschlossen.
     * Sortiert nach Bestelldatum aufsteigend (älteste zuerst).
     */
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

    /**
     * Sucht einen Artikel über seinen EAN/GTIN-Code.
     * Gibt Artikel-Stammdaten + Hauptbild für die Scan-Ansicht zurück.
     * Berücksichtigt: COALESCE Vater/Kind-Name, charge_pflicht-Flag.
     * Gibt false zurück wenn der Code nicht gefunden wird.
     */
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

    /**
     * Gibt alle offenen Bestellpositionen für einen Artikel zurück.
     * Nur Positionen bei denen noch Menge erwartet wird (menge_eingegangen < menge_bestellt).
     * Sortiert nach Bestelldatum (älteste Bestellung zuerst buchen).
     */
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

    /**
     * Gibt eine einzelne Bestellposition zurück (mit Bestellkopf-Daten).
     * Wird beim Buchen benötigt um lieferant_id und ek_preis für die Lagerbewegung zu kennen.
     */
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

    /**
     * Gibt alle Positionen einer Bestellung mit Artikel-Details zurück.
     * Für die Detailansicht im Wareneingang: Artikel-Name, Variante, charge_pflicht, Hauptbild.
     */
    public function findPositionenMitArtikel(int $bestellungId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                bp.*,
                COALESCE(vater.name, a.name)                     AS artikel_name,
                COALESCE(vater.artikelnummer, a.artikelnummer)    AS artikel_nr,
                a.artikelnummer                                   AS kind_artikelnummer,
                CASE WHEN a.vaterartikel_id IS NOT NULL THEN a.name END AS variante_name,
                a.charge_pflicht,
                (SELECT dateiname
                 FROM artikel_bilder
                 WHERE artikel_id = COALESCE(a.vaterartikel_id, a.id) AND position = 0
                 LIMIT 1)                                         AS hauptbild,
                (SELECT code FROM artikel_codes
                 WHERE artikel_id = a.id AND typ = 'GTIN13' LIMIT 1) AS ean
            FROM bestellung_positionen bp
            JOIN  artikel a ON a.id = bp.artikel_id
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            WHERE bp.bestellung_id = :bestellung_id
            ORDER BY bp.id
        ");
        $stmt->execute(['bestellung_id' => $bestellungId]);
        return $stmt->fetchAll();
    }

    /**
     * Erhöht menge_eingegangen einer Position um die gebuchte Menge.
     * Wird nach jeder Wareneingangs-Buchung aufgerufen.
     */
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

    /**
     * Legt einen Eingang-Record an der Position + Lagerbewegung verknüpft.
     * Verbindet bestellung_eingaenge.bewegung_id mit lager_bewegungen für lückenlose Rückverfolgung.
     * Gibt die neue ID zurück.
     */
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

    /**
     * Prüft ob alle Positionen einer Bestellung vollständig eingegangen sind.
     * Gibt true zurück wenn keine offene Position mehr vorhanden ist
     * (menge_eingegangen >= menge_bestellt für alle nicht-gestrichenen Positionen).
     * Steuert ob die Bestellung auf "erledigt" oder "teilgeliefert" gesetzt wird.
     */
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

    /** Setzt den Status einer Bestellung (teilgeliefert oder erledigt). */
    public function updateBestellungStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare("UPDATE bestellungen SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /**
     * Streicht alle Positionen mit noch offener Menge als "gestrichen".
     * DROPS-Modell: Lieferant liefert was er hat, Rest wird nicht nachgeliefert.
     * Optional: Gutschrift-Notiz und -Betrag werden an der Bestellung gespeichert,
     * wenn der Lieferant einen Preisnachlass für die fehlende Ware gewährt.
     */
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

    /**
     * Gibt vorhandene Chargen eines Artikels zurück (für Charge-Auswahl beim Einbuchen).
     * Nur Chargen mit Bestand > 0 — leere Chargen sind nicht mehr relevant für neue Buchungen.
     */
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

    /** Gibt alle aktiven Lager zurück, sortiert nach ID (Standard-Lager zuerst). */
    public function findAlleLager(): array
    {
        $stmt = $this->db->query("SELECT id, name FROM lager WHERE aktiv = 1 ORDER BY id");
        return $stmt->fetchAll();
    }
}
