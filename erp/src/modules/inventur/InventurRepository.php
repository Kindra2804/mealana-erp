<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * InventurRepository – Datenzugriff für Inventur-Läufe (Kopftabelle)
 *
 * Scope ist polymorph (scope_tabelle/scope_id, wie aktivitaeten.referenz_tabelle/
 * referenz_id) — zeigt je nach Lauf auf lager, lagerplaetze, kategorien, artikel
 * oder mietfaecher. scope_bezeichnung ist ein Namens-Snapshot zum Startzeitpunkt.
 */
class InventurRepository
{
    private PDO $db;

    /** Erlaubte Scope-Tabellen + die Spalte, aus der die Bezeichnung gelesen wird. */
    private const SCOPE_TABELLEN = [
        'lager'        => ['tabelle' => 'lager',        'bezeichnung_spalte' => 'name'],
        'lagerplaetze' => ['tabelle' => 'lagerplaetze',  'bezeichnung_spalte' => 'bezeichnung'],
        'kategorien'   => ['tabelle' => 'kategorien',    'bezeichnung_spalte' => 'name'],
        'artikel'      => ['tabelle' => 'artikel',       'bezeichnung_spalte' => 'name'],
        'mietfaecher'  => ['tabelle' => 'mietfaecher',   'bezeichnung_spalte' => 'fach_bezeichnung'],
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function gueltigeScopeTabellen(): array
    {
        return array_keys(self::SCOPE_TABELLEN);
    }

    /**
     * Löst die Bezeichnung eines Scope-Ziels auf (für den Namens-Snapshot beim Start).
     * Gibt null zurück wenn scope_tabelle ungültig oder scope_id nicht gefunden.
     */
    public function findScopeBezeichnung(string $scopeTabelle, int $scopeId): ?string
    {
        if (!isset(self::SCOPE_TABELLEN[$scopeTabelle])) {
            return null;
        }
        $def = self::SCOPE_TABELLEN[$scopeTabelle];
        $stmt = $this->db->prepare("SELECT {$def['bezeichnung_spalte']} AS bezeichnung FROM {$def['tabelle']} WHERE id = :id");
        $stmt->execute(['id' => $scopeId]);
        $row = $stmt->fetch();
        return $row ? $row['bezeichnung'] : null;
    }

    /** Gibt alle Inventur-Läufe zurück, neueste zuerst, mit Ersteller-Name. */
    public function findAlle(): array
    {
        $stmt = $this->db->query("
            SELECT il.*, b.formularname AS benutzer_name
            FROM inventur_laeufe il
            LEFT JOIN benutzer b ON b.id = il.benutzer_id
            ORDER BY il.gestartet_am DESC
        ");
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM inventur_laeufe WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function insert(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO inventur_laeufe (
                scope_tabelle, scope_id, scope_bezeichnung, blind_modus,
                vorgaenger_lauf_id, notiz, benutzer_id
            ) VALUES (
                :scope_tabelle, :scope_id, :scope_bezeichnung, :blind_modus,
                :vorgaenger_lauf_id, :notiz, :benutzer_id
            )
        ");
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function setStatus(int $id, string $status, bool $mitBeendetAm): bool
    {
        $sql = $mitBeendetAm
            ? "UPDATE inventur_laeufe SET status = :status, beendet_am = NOW() WHERE id = :id"
            : "UPDATE inventur_laeufe SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['status' => $status, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Auswahllisten für die Scope-Auswahl beim Start eines Laufs
    // -------------------------------------------------------------------------

    public function findAlleLagerFuerAuswahl(): array
    {
        return $this->db->query("SELECT id, name FROM lager WHERE aktiv = 1 ORDER BY name")->fetchAll();
    }

    public function findAlleLagerplaetzeFuerAuswahl(): array
    {
        return $this->db->query("
            SELECT lp.id, lp.bezeichnung, l.name AS lager_name
            FROM lagerplaetze lp
            JOIN lager l ON l.id = lp.lager_id
            WHERE lp.aktiv = 1
            ORDER BY l.name, lp.bezeichnung
        ")->fetchAll();
    }

    public function findAlleKategorienFuerAuswahl(): array
    {
        return $this->db->query("SELECT id, name FROM kategorien WHERE aktiv = 1 ORDER BY name")->fetchAll();
    }

    public function findAlleMietfaecherFuerAuswahl(): array
    {
        return $this->db->query("SELECT id, fach_bezeichnung FROM mietfaecher WHERE aktiv = 1 ORDER BY fach_bezeichnung")->fetchAll();
    }

    /** Typeahead-Suche für die Artikel-Scope-Auswahl (Name oder Artikelnummer, max. 20 Treffer). */
    public function findArtikelFuerSuche(string $suche): array
    {
        $stmt = $this->db->prepare("
            SELECT a.id, a.name, a.artikelnummer
            FROM artikel a
            WHERE a.aktiv = 1
              AND (a.name LIKE :suche OR a.artikelnummer LIKE :suche)
            ORDER BY a.name
            LIMIT 20
        ");
        $stmt->execute(['suche' => '%' . $suche . '%']);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Zählliste (Slice 2): Soll-Liste je Scope + Positionen-CRUD
    // -------------------------------------------------------------------------

    /**
     * Soll-Liste für "Ganzes Lager": alle lagerbestand-Zeilen dieses Lagers,
     * Soll = lb.bestand (Gesamtmenge, unabhängig von Lagerplatz-Zuordnung).
     */
    public function findSollListeLager(int $lagerId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                lb.artikel_id, lb.lager_id, l.name AS lager_name,
                lb.charge, lb.bestand AS soll_menge,
                COALESCE(vater.name, a.name) AS artikel_name,
                COALESCE(vater.artikelnummer, a.artikelnummer) AS artikelnummer
            FROM lagerbestand lb
            JOIN lager l ON l.id = lb.lager_id
            JOIN artikel a ON a.id = lb.artikel_id
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            WHERE lb.lager_id = :lager_id
            ORDER BY artikel_name
        ");
        $stmt->execute(['lager_id' => $lagerId]);
        return $stmt->fetchAll();
    }

    /**
     * Soll-Liste für "Ein Lagerplatz": bereits zugeordnete Mengen aus
     * lagerbestand_lagerplaetze. Beim allerersten Zählgang eines Platzes
     * bewusst LEER (siehe project_inventur_konzept) — der Zähler erfasst frei
     * per Scan/Suche, es gibt ja noch keine Zuordnung zu vergleichen.
     */
    public function findSollListeLagerplatz(int $lagerplatzId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                lb.artikel_id, lb.lager_id, l.name AS lager_name,
                llp.lagerplatz_id, lb.charge, llp.menge AS soll_menge,
                COALESCE(vater.name, a.name) AS artikel_name,
                COALESCE(vater.artikelnummer, a.artikelnummer) AS artikelnummer
            FROM lagerbestand_lagerplaetze llp
            JOIN lagerbestand lb ON lb.id = llp.lagerbestand_id
            JOIN lager l ON l.id = lb.lager_id
            JOIN artikel a ON a.id = lb.artikel_id
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            WHERE llp.lagerplatz_id = :lagerplatz_id
            ORDER BY artikel_name
        ");
        $stmt->execute(['lagerplatz_id' => $lagerplatzId]);
        return $stmt->fetchAll();
    }

    /**
     * Soll-Liste für "Eine Kategorie": alle Artikel dieser Kategorie, mit ihrem
     * Lagerbestand über ALLE Lager (Scope legt kein Lager fest — Jacky-Entscheidung
     * 2026-07-18: über alle Lager, mit Lager-Spalte in der Anzeige).
     */
    public function findSollListeKategorie(int $kategorieId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                lb.artikel_id, lb.lager_id, l.name AS lager_name,
                lb.charge, lb.bestand AS soll_menge,
                COALESCE(vater.name, a.name) AS artikel_name,
                COALESCE(vater.artikelnummer, a.artikelnummer) AS artikelnummer
            FROM artikel_kategorien ak
            JOIN artikel a ON a.id = ak.artikel_id
            JOIN lagerbestand lb ON lb.artikel_id = a.id
            JOIN lager l ON l.id = lb.lager_id
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            WHERE ak.kategorie_id = :kategorie_id
            ORDER BY artikel_name, lager_name
        ");
        $stmt->execute(['kategorie_id' => $kategorieId]);
        return $stmt->fetchAll();
    }

    /** Soll-Liste für "Ein einzelner Artikel": über alle Lager (gleiche Begründung wie Kategorie). */
    public function findSollListeArtikel(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                lb.artikel_id, lb.lager_id, l.name AS lager_name,
                lb.charge, lb.bestand AS soll_menge,
                COALESCE(vater.name, a.name) AS artikel_name,
                COALESCE(vater.artikelnummer, a.artikelnummer) AS artikelnummer
            FROM lagerbestand lb
            JOIN artikel a ON a.id = lb.artikel_id
            JOIN lager l ON l.id = lb.lager_id
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            WHERE lb.artikel_id = :artikel_id
            ORDER BY lager_name
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    /**
     * Gibt zurück, welches Lager zu einem Lagerplatz gehört (für die Positionserfassung,
     * wo lager_id gebraucht wird auch wenn der Scope nur den Lagerplatz nennt).
     */
    public function findLagerIdFuerLagerplatz(int $lagerplatzId): ?int
    {
        $stmt = $this->db->prepare("SELECT lager_id FROM lagerplaetze WHERE id = :id");
        $stmt->execute(['id' => $lagerplatzId]);
        $lagerId = $stmt->fetchColumn();
        return $lagerId !== false ? (int)$lagerId : null;
    }

    /** Alle bereits erfassten Positionen eines Laufs (für die "schon gezählt"-Anzeige). */
    public function findPositionenFuerLauf(int $laufId): array
    {
        $stmt = $this->db->prepare("
            SELECT ip.*, COALESCE(vater.name, a.name) AS artikel_name,
                   COALESCE(vater.artikelnummer, a.artikelnummer) AS artikelnummer,
                   l.name AS lager_name, lp.bezeichnung AS lagerplatz_bezeichnung,
                   b.formularname AS gezaehlt_von_name
            FROM inventur_positionen ip
            JOIN artikel a ON a.id = ip.artikel_id
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            JOIN lager l ON l.id = ip.lager_id
            LEFT JOIN lagerplaetze lp ON lp.id = ip.lagerplatz_id
            LEFT JOIN benutzer b ON b.id = ip.gezaehlt_von
            WHERE ip.inventur_lauf_id = :lauf_id
            ORDER BY ip.gezaehlt_am DESC
        ");
        $stmt->execute(['lauf_id' => $laufId]);
        return $stmt->fetchAll();
    }

    /**
     * Sucht eine bestehende Position exakt nach Schlüssel (Lauf/Artikel/Lager/Lagerplatz/Charge).
     * Manueller Match statt DB-UNIQUE-Constraint, weil lagerplatz_id und charge
     * beide NULL sein können (NULL != NULL in SQL-Vergleichen, siehe Migration 137).
     */
    public function findPosition(int $laufId, int $artikelId, int $lagerId, ?int $lagerplatzId, ?string $charge): array|false
    {
        $where = ['inventur_lauf_id = :lauf_id', 'artikel_id = :artikel_id', 'lager_id = :lager_id'];
        $params = ['lauf_id' => $laufId, 'artikel_id' => $artikelId, 'lager_id' => $lagerId];

        $where[] = $lagerplatzId !== null ? 'lagerplatz_id = :lagerplatz_id' : 'lagerplatz_id IS NULL';
        if ($lagerplatzId !== null) $params['lagerplatz_id'] = $lagerplatzId;

        $where[] = $charge !== null ? 'charge = :charge' : 'charge IS NULL';
        if ($charge !== null) $params['charge'] = $charge;

        $stmt = $this->db->prepare("SELECT * FROM inventur_positionen WHERE " . implode(' AND ', $where));
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function insertPosition(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO inventur_positionen (
                inventur_lauf_id, artikel_id, lager_id, lagerplatz_id, charge,
                soll_menge, ist_menge, status, notiz, gezaehlt_von, gezaehlt_am
            ) VALUES (
                :inventur_lauf_id, :artikel_id, :lager_id, :lagerplatz_id, :charge,
                :soll_menge, :ist_menge, :status, :notiz, :gezaehlt_von, NOW()
            )
        ");
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function updatePosition(int $id, float $istMenge, ?string $notiz, int $benutzerId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE inventur_positionen
            SET ist_menge = :ist_menge, status = 'gezaehlt', notiz = :notiz,
                gezaehlt_von = :gezaehlt_von, gezaehlt_am = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'ist_menge'    => $istMenge,
            'notiz'        => $notiz,
            'gezaehlt_von' => $benutzerId,
            'id'           => $id,
        ]);
        return $stmt->rowCount() > 0;
    }
}
