<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * KategorieRepository – Datenzugriff für den Kategorie-Baum und Artikel-Zuweisungen
 *
 * Kategorien bilden einen beliebig tiefen Baum (parent_id selbst-referenziell).
 * Aktions-Kategorien (ist_aktions_kategorie = 1) steuern welche Artikel einer Aktion unterliegen.
 *
 * Wichtige Methoden:
 *   findAllMitEltern()                → Flat-Liste mit Aktions-Indikatoren (⏰-Symbol in UI)
 *   updateArtikelKategoriezuweisungen → Transaktionaler Replace + Aktionspreise-Bereinigung
 *   syncKategorienZuKindern()         → Synchronisiert Kategorien eines Vaters zu allen Kindern
 *   findAlleKinderIds()               → Rekursive CTE-Query für alle Nachkommen
 *   deleteKategorie()                 → Löscht Baum-Ast mit optionalem Artikel-Verschieben
 */
class KategorieRepository
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
                id,
                name
            FROM kategorien
            WHERE aktiv = 1
            ORDER BY name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Alle aktiven Kategorien als Flat-Liste für den Baum-Aufbau in ArtikelService::getKategorienBaum().
     * Berechnet pro Kategorie drei Aktions-Spalten via LEFT JOIN auf aktionen_kategorien + aktionen:
     *   aktion_aktiv   → 1 wenn heute aktive Aktion (gestartet + Datum zwischen ab/bis)
     *   aktion_zukunft → 1 wenn zukünftige Aktion existiert
     *   aktion_info    → GROUP_CONCAT der Aktionsnamen+Zeiträume für Hover-Tooltip
     */
    public function findAllMitEltern(): array
    {
        $stmt = $this->db->query("
            SELECT k.id, k.parent_id, k.name, k.sortierung,
                k.ist_aktions_kategorie,
                COUNT(DISTINCT a.id) AS artikel_anzahl,
                MAX(CASE WHEN akt2.gestartet = 1 AND CURDATE() BETWEEN ak2.gueltig_ab AND ak2.gueltig_bis THEN 1 ELSE 0 END) AS aktion_aktiv,
                MAX(CASE WHEN ak2.gueltig_ab > CURDATE() THEN 1 ELSE 0 END) AS aktion_zukunft,
                GROUP_CONCAT(
                    DISTINCT CONCAT(akt2.name, ': ', DATE_FORMAT(ak2.gueltig_ab, '%d.%m.%Y'), ' – ', DATE_FORMAT(ak2.gueltig_bis, '%d.%m.%Y'))
                    ORDER BY ak2.gueltig_ab ASC
                    SEPARATOR ' | '
                ) AS aktion_info
            FROM kategorien k
            LEFT JOIN artikel_kategorien ak ON ak.kategorie_id = k.id
            LEFT JOIN artikel vater ON vater.id = ak.artikel_id AND vater.aktiv = 1
            LEFT JOIN artikel a ON a.aktiv = 1 AND (
                (a.id = vater.id AND vater.ist_vater = 0)
                OR
                (a.vaterartikel_id = vater.id)
            )
            LEFT JOIN aktionen_kategorien ak2 ON ak2.kategorie_id = k.id
            LEFT JOIN aktionen akt2 ON akt2.id = ak2.aktion_id
            WHERE k.aktiv = 1
            GROUP BY k.id
            ORDER BY k.sortierung ASC, k.name ASC
        ");
        return $stmt->fetchAll();
    }

    public function countByKategorie(int $kategorieId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT a.id)
            FROM artikel a
            INNER JOIN artikel_kategorien ak ON ak.artikel_id = a.id
            WHERE ak.kategorie_id = :kid
              AND a.aktiv = 1
              AND a.vaterartikel_id IS NULL
              AND a.zustand_vater_id IS NULL
        ");
        $stmt->execute(['kid' => $kategorieId]);
        return (int) $stmt->fetchColumn();
    }

    public function findByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                k.id, 
                k.name 
            FROM kategorien k
            INNER JOIN artikel_kategorien ak ON k.id = ak.kategorie_id
            WHERE ak.artikel_id = :artikel_id
            AND k.aktiv = 1
            ORDER BY k.name ASC
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    /**
     * Ersetzt alle Kategorie-Zuweisungen eines Artikels in einer Transaktion.
     *
     * Ablauf:
     * 1. Alte Zuweisungen lesen → entfernte vs. neue Kategorien berechnen
     * 2. Alle alten löschen, alle neuen einfügen
     * 3. Aktionspreise für entfernte Kategorien bereinigen
     *    (Artikel nicht mehr in Kategorie → Aktionspreis obsolet)
     * 4. Aktionshinweise für neu zugewiesene Kategorien ermitteln
     *    (damit die View ein Modal für Aktionspreise anzeigen kann)
     *
     * @return array Aktionshinweise-Zeilen [aktion_id, aktion_name, ist_aktiv, naechster_start]
     */
    public function updateArtikelKategoriezuweisungen(int $artikelId, array $kategorieIds): array
    {
        try {
            $this->db->beginTransaction();

            // Bestehende Zuweisungen ermitteln um entfernte/neue Kategorien zu kennen
            $stmt = $this->db->prepare("SELECT kategorie_id FROM artikel_kategorien WHERE artikel_id = :artikel_id");
            $stmt->execute(['artikel_id' => $artikelId]);
            $alteKatIds      = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $entfernteKatIds = array_values(array_diff($alteKatIds, $kategorieIds));
            $neueKatIds      = array_values(array_diff($kategorieIds, $alteKatIds));

            $stmt = $this->db->prepare("DELETE FROM artikel_kategorien WHERE artikel_id = :artikel_id");
            $stmt->execute(['artikel_id' => $artikelId]);

            $stmt = $this->db->prepare("INSERT INTO artikel_kategorien (artikel_id, kategorie_id) VALUES (:artikel_id, :kategorie_id)");
            foreach ($kategorieIds as $kategorieId) {
                $stmt->execute(['artikel_id' => $artikelId, 'kategorie_id' => $kategorieId]);
            }

            // Aktionspreise für entfernte Kategorien bereinigen
            if (!empty($entfernteKatIds)) {
                $pl       = implode(',', array_fill(0, count($entfernteKatIds), '?'));
                $aktStmt  = $this->db->prepare("SELECT DISTINCT aktion_id FROM aktionen_kategorien WHERE kategorie_id IN ($pl)");
                $aktStmt->execute($entfernteKatIds);
                $aktionIds = $aktStmt->fetchAll(\PDO::FETCH_COLUMN);

                if (!empty($aktionIds)) {
                    $pl2 = implode(',', array_fill(0, count($aktionIds), '?'));
                    $this->db->prepare("
                        DELETE FROM aktionen_artikel_preise
                        WHERE artikel_id = ? AND aktion_id IN ($pl2)
                    ")->execute(array_merge([$artikelId], $aktionIds));
                }
            }

            // Aktive + geplante (nicht abgelaufene) Aktionen für neu zugewiesene Kategorien
            $aktionsHinweise = [];
            if (!empty($neueKatIds)) {
                $pl = implode(',', array_fill(0, count($neueKatIds), '?'));
                $hinweisStmt = $this->db->prepare("
                    SELECT
                        a.id   AS aktion_id,
                        a.name AS aktion_name,
                        MAX(CASE WHEN CURDATE() BETWEEN ak.gueltig_ab AND ak.gueltig_bis THEN 1 ELSE 0 END) AS ist_aktiv,
                        MIN(ak.gueltig_ab) AS naechster_start
                    FROM aktionen a
                    JOIN aktionen_kategorien ak ON ak.aktion_id = a.id
                    WHERE ak.kategorie_id IN ($pl)
                      AND a.gestartet = 1
                      AND ak.gueltig_bis >= CURDATE()
                    GROUP BY a.id, a.name
                ");
                $hinweisStmt->execute($neueKatIds);
                $aktionsHinweise = $hinweisStmt->fetchAll();
            }

            $this->db->commit();
            return $aktionsHinweise;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Synchronisiert die Kategorien des Vaters zu allen seinen Kind-Artikeln.
     * Löscht alle Kind-Kategorien und setzt sie neu — Kinder haben immer dieselben Kategorien wie der Vater.
     * Wird nach saveKategorien() am Vater aufgerufen.
     */
    public function syncKategorienZuKindern(int $vaterId, array $kategorieIds): void
    {
        $stmt = $this->db->prepare("SELECT id FROM artikel WHERE vaterartikel_id = :vater_id");
        $stmt->execute(['vater_id' => $vaterId]);
        $kindIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($kindIds)) return;

        $pl = implode(',', array_fill(0, count($kindIds), '?'));
        $this->db->prepare("DELETE FROM artikel_kategorien WHERE artikel_id IN ($pl)")->execute($kindIds);

        if (empty($kategorieIds)) return;

        $stmt = $this->db->prepare("INSERT INTO artikel_kategorien (artikel_id, kategorie_id) VALUES (?, ?)");
        foreach ($kindIds as $kindId) {
            foreach ($kategorieIds as $katId) {
                $stmt->execute([(int)$kindId, (int)$katId]);
            }
        }
    }

    public function bulkAddKategorie(array $artikelIds, int $kategorieId): void
    {
        $stmt = $this->db->prepare("INSERT IGNORE INTO artikel_kategorien (artikel_id, kategorie_id) VALUES (?, ?)");
        foreach ($artikelIds as $aid) {
            $stmt->execute([(int)$aid, $kategorieId]);
        }
        // Kinder von Vater-Artikeln mitziehen
        $kindStmt = $this->db->prepare("SELECT id FROM artikel WHERE vaterartikel_id = ?");
        $insStmt  = $this->db->prepare("INSERT IGNORE INTO artikel_kategorien (artikel_id, kategorie_id) VALUES (?, ?)");
        foreach ($artikelIds as $aid) {
            $kindStmt->execute([(int)$aid]);
            foreach ($kindStmt->fetchAll(\PDO::FETCH_COLUMN) as $kindId) {
                $insStmt->execute([(int)$kindId, $kategorieId]);
            }
        }
    }

    public function insert(string $name, ?int $parentId = null, bool $istAktionsKategorie = false): int
    {
        $stmt = $this->db->prepare("INSERT INTO kategorien (name, parent_id, ist_aktions_kategorie) VALUES (:name, :parent_id, :iak)");
        $stmt->execute(['name' => $name, 'parent_id' => $parentId, 'iak' => $istAktionsKategorie ? 1 : 0]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT id, parent_id, name, sortierung, ist_aktions_kategorie FROM kategorien WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getSiblingsWithSort(?int $parentId): array
    {
        if ($parentId === null) {
            $stmt = $this->db->query("
                SELECT id, sortierung FROM kategorien
                WHERE parent_id IS NULL AND aktiv = 1
                ORDER BY COALESCE(sortierung, 0), name
            ");
        } else {
            $stmt = $this->db->prepare("
                SELECT id, sortierung FROM kategorien
                WHERE parent_id = :pid AND aktiv = 1
                ORDER BY COALESCE(sortierung, 0), name
            ");
            $stmt->execute(['pid' => $parentId]);
        }
        return $stmt->fetchAll();
    }

    public function updateSortierung(int $id, int $sort): void
    {
        $stmt = $this->db->prepare("UPDATE kategorien SET sortierung = :sort WHERE id = :id");
        $stmt->execute(['sort' => $sort, 'id' => $id]);
    }

    public function update(int $id, string $name, ?int $parentId, bool $istAktionsKategorie = false): bool
    {
        $stmt = $this->db->prepare("UPDATE kategorien SET name = :name, parent_id = :parent_id, ist_aktions_kategorie = :iak WHERE id = :id");
        return $stmt->execute(['name' => $name, 'parent_id' => $parentId, 'iak' => $istAktionsKategorie ? 1 : 0, 'id' => $id]);
    }

    /**
     * Gibt alle Nachkommen-IDs einer Kategorie rekursiv zurück (WITH RECURSIVE CTE).
     * Benötigt für: Lösch-Vorschau, Baum-Verschieben-Validierung (kein Zirkel), Massen-Löschen.
     */
    public function findAlleKinderIds(int $id): array
    {
        // Alle Nachkommen per rekursiver CTE (MariaDB 10.2+)
        $stmt = $this->db->prepare("
            WITH RECURSIVE nachkommen AS (
                SELECT id FROM kategorien WHERE parent_id = :id
                UNION ALL
                SELECT k.id FROM kategorien k
                INNER JOIN nachkommen n ON k.parent_id = n.id
            )
            SELECT id FROM nachkommen
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function findArtikelNurInDiesenKategorien(array $kategorieIds): array
    {
        if (empty($kategorieIds)) return [];
        $pl = implode(',', array_fill(0, count($kategorieIds), '?'));
        $stmt = $this->db->prepare("
            SELECT a.id, a.artikelnummer, a.name
            FROM artikel a
            WHERE a.vaterartikel_id IS NULL
              AND EXISTS (
                  SELECT 1 FROM artikel_kategorien ak WHERE ak.artikel_id = a.id AND ak.kategorie_id IN ($pl)
              )
              AND NOT EXISTS (
                  SELECT 1 FROM artikel_kategorien ak2 WHERE ak2.artikel_id = a.id AND ak2.kategorie_id NOT IN ($pl)
              )
            ORDER BY a.artikelnummer
        ");
        $stmt->execute(array_merge($kategorieIds, $kategorieIds));
        return $stmt->fetchAll();
    }

    /**
     * Löscht eine Kategorie mit allen Nachkommen in einer Transaktion.
     * Wenn verschiebeZuParentId gesetzt: Artikel der gelöschten Kategorien werden der
     * Eltern-Kategorie zugewiesen (INSERT IGNORE verhindert Duplikat-Fehler).
     */
    public function deleteKategorie(int $id, ?int $verschiebeZuParentId = null): void
    {
        $this->db->beginTransaction();
        try {
            $alleIds      = array_merge([$id], $this->findAlleKinderIds($id));
            $placeholders = implode(',', array_fill(0, count($alleIds), '?'));

            if ($verschiebeZuParentId !== null) {
                $this->db->prepare("
                    INSERT IGNORE INTO artikel_kategorien (artikel_id, kategorie_id)
                    SELECT artikel_id, ? FROM artikel_kategorien WHERE kategorie_id IN ($placeholders)
                ")->execute(array_merge([$verschiebeZuParentId], $alleIds));
            }

            $this->db->prepare("DELETE FROM artikel_kategorien WHERE kategorie_id IN ($placeholders)")
                ->execute($alleIds);

            $this->db->prepare("DELETE FROM kategorien WHERE id IN ($placeholders)")
                ->execute($alleIds);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
