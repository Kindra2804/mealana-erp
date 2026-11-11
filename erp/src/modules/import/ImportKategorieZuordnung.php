<?php
require_once __DIR__ . '/../../core/Database.php';

/**
 * Kategorie-Zuordnung für den JTL-Artikelimport (Migration 230).
 *
 * Der JTL-Export hat je Zeile die Spalten `KategorieIDs` und `Kategorien` (pipe-getrennt, paarweise
 * gleich lang). Namen sind NICHT eindeutig (z.B. „Amigurumi“ oder „Rundnadeln“ in mehreren Zweigen),
 * daher wird immer über die JTL-ID zugeordnet. Die Wahl im Import wird gemerkt und beim nächsten
 * Import vorausgefüllt (Jacky 2026-11-11: lieber Schritt für Schritt selbst zuordnen als eine
 * automatische Wurzelkategorie je Shop).
 */
class ImportKategorieZuordnung
{
    private PDO $db;

    public function __construct(private string $quelle = 'jtl')
    {
        $this->db = Database::getInstance();
    }

    /** JTL-Kategorie-IDs einer CSV-Zeile (Reihenfolge wie in der Datei, ohne Doppelte). */
    public static function idsAusZeile(array $row): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', explode('|', (string)($row['KategorieIDs'] ?? ''))),
            'strlen'
        )));
    }

    /**
     * Übersicht der JTL-Kategorien, die in den gegebenen Zeilen vorkommen.
     *
     * @param array $zeilen  Artikelnummer => CSV-Zeile
     * @return array fremd_id => ['name' => …, 'anzahl' => Zeilen, 'beispiele' => [Artikelname, …], 'mit' => [andere Namen in denselben Zeilen]]
     */
    public static function sammle(array $zeilen): array
    {
        $liste = [];
        foreach ($zeilen as $row) {
            $ids   = array_map('trim', explode('|', (string)($row['KategorieIDs'] ?? '')));
            $namen = array_map('trim', explode('|', (string)($row['Kategorien'] ?? '')));
            if (count($ids) !== count($namen)) continue;   // kaputte Zeile: lieber nichts raten
            foreach ($ids as $i => $id) {
                if ($id === '') continue;
                $e = &$liste[$id];
                $e['name']   = $e['name'] ?? $namen[$i];
                $e['anzahl'] = ($e['anzahl'] ?? 0) + 1;
                $e['beispiele'] = $e['beispiele'] ?? [];
                $e['mit']       = $e['mit'] ?? [];
                $artName = trim((string)($row['Artikelname'] ?? ''));
                if ($artName !== '' && count($e['beispiele']) < 3 && !in_array($artName, $e['beispiele'], true)) {
                    $e['beispiele'][] = $artName;
                }
                // Nachbarn in derselben Zeile helfen, gleichnamige Kategorien zu unterscheiden
                foreach ($namen as $j => $n) {
                    if ($j !== $i && $n !== '' && $n !== $namen[$i]) $e['mit'][$n] = ($e['mit'][$n] ?? 0) + 1;
                }
                unset($e);
            }
        }
        foreach ($liste as &$e) {
            arsort($e['mit']);
            $e['mit'] = array_slice(array_keys($e['mit']), 0, 4);
        }
        unset($e);
        uasort($liste, fn($a, $b) => strcasecmp($a['name'], $b['name']) ?: $b['anzahl'] <=> $a['anzahl']);
        return $liste;
    }

    /**
     * Gespeicherte Zuordnungen für diese Fremd-IDs.
     * @return array fremd_id => kategorie_id (int) oder 0 (= gemerkt als „nicht zuordnen“); nicht gemerkte fehlen.
     */
    public function lade(array $fremdIds): array
    {
        if (!$fremdIds) return [];
        $platz = implode(',', array_fill(0, count($fremdIds), '?'));
        $stmt = $this->db->prepare("SELECT fremd_id, kategorie_id FROM import_kategorie_zuordnung WHERE quelle = ? AND fremd_id IN ($platz)");
        $stmt->execute(array_merge([$this->quelle], array_map('strval', $fremdIds)));
        $erg = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $erg[$r['fremd_id']] = (int)$r['kategorie_id'];
        return $erg;
    }

    /** Merkt die Wahl aus dem Import. $wahl: fremd_id => kategorie_id (0/'' = nicht zuordnen). */
    public function speichere(array $wahl, array $namen): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO import_kategorie_zuordnung (quelle, fremd_id, fremd_name, kategorie_id)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE fremd_name = VALUES(fremd_name), kategorie_id = VALUES(kategorie_id)
        ");
        foreach ($wahl as $fremdId => $katId) {
            $katId = (int)$katId;
            $stmt->execute([$this->quelle, (string)$fremdId, mb_substr((string)($namen[$fremdId] ?? ''), 0, 255), $katId > 0 ? $katId : null]);
        }
    }
}
