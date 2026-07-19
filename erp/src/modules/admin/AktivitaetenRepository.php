<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * AktivitaetenRepository – liest das unveränderliche Aktivitäten-Log (`aktivitaeten`).
 *
 * Reines Lese-Modul: aktivitaeten wird ausschließlich über Logger::log() befüllt
 * (siehe src/core/logger.php), hier gibt es kein insert()/update().
 *
 * "Modul" ist keine eigene Spalte, sondern der Teil von `aktion` vor dem ersten
 * Punkt (Konvention "modul.aktion", z.B. "artikel.bearbeiten" -> Modul "artikel").
 */
class AktivitaetenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Für die Logger-Zeile in der Shell (shell_bottom.php): die letzten N Einträge. */
    public function findLetzte(int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT a.*, b.formularname AS benutzer_name
            FROM aktivitaeten a
            LEFT JOIN benutzer b ON b.id = a.benutzer_id
            ORDER BY a.erstellt_am DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @param array{benutzer_id?:int,modul?:string,tabelle?:string,stufe?:string,von?:string,bis?:string,suche?:string} $filter */
    public function findGefiltert(array $filter, int $offset, int $proSeite): array
    {
        [$where, $params] = $this->buildWhere($filter);
        $stmt = $this->db->prepare("
            SELECT a.*, b.formularname AS benutzer_name
            FROM aktivitaeten a
            LEFT JOIN benutzer b ON b.id = a.benutzer_id
            $where
            ORDER BY a.erstellt_am DESC
            LIMIT :offset, :pro_seite
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue('pro_seite', $proSeite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countGefiltert(array $filter): int
    {
        [$where, $params] = $this->buildWhere($filter);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM aktivitaeten a $where");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function buildWhere(array $filter): array
    {
        $bedingungen = [];
        $params = [];

        if (!empty($filter['benutzer_id'])) {
            $bedingungen[] = 'a.benutzer_id = :benutzer_id';
            $params['benutzer_id'] = (int)$filter['benutzer_id'];
        }
        if (!empty($filter['modul'])) {
            $bedingungen[] = "a.aktion LIKE :modul";
            $params['modul'] = $filter['modul'] . '.%';
        }
        if (!empty($filter['tabelle'])) {
            $bedingungen[] = 'a.referenz_tabelle = :tabelle';
            $params['tabelle'] = $filter['tabelle'];
        }
        if (!empty($filter['stufe'])) {
            $bedingungen[] = 'a.stufe = :stufe';
            $params['stufe'] = $filter['stufe'];
        }
        if (!empty($filter['von'])) {
            $bedingungen[] = 'a.erstellt_am >= :von';
            $params['von'] = $filter['von'] . ' 00:00:00';
        }
        if (!empty($filter['bis'])) {
            $bedingungen[] = 'a.erstellt_am <= :bis';
            $params['bis'] = $filter['bis'] . ' 23:59:59';
        }
        if (!empty($filter['suche'])) {
            $bedingungen[] = '(a.aktion LIKE :suche OR a.details LIKE :suche2)';
            $params['suche']  = '%' . $filter['suche'] . '%';
            $params['suche2'] = '%' . $filter['suche'] . '%';
        }

        $where = $bedingungen ? ('WHERE ' . implode(' AND ', $bedingungen)) : '';
        return [$where, $params];
    }

    /** Für das Modul-Filter-Dropdown: Teil von `aktion` vor dem ersten Punkt. */
    public function findModule(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT SUBSTRING_INDEX(aktion, '.', 1) AS modul
            FROM aktivitaeten
            ORDER BY modul
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Für das Tabellen-Filter-Dropdown. */
    public function findReferenzTabellen(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT referenz_tabelle
            FROM aktivitaeten
            WHERE referenz_tabelle IS NOT NULL
            ORDER BY referenz_tabelle
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
