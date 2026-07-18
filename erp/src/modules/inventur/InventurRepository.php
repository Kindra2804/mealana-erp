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
}
