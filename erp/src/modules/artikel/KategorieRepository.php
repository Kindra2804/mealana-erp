<?php

require_once __DIR__ . '/../../core/Database.php';

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

    public function findAllMitEltern(): array
    {
        $stmt = $this->db->query("
            SELECT k.id, k.parent_id, k.name, k.sortierung,
                COUNT(DISTINCT a.id) AS artikel_anzahl
            FROM kategorien k
            LEFT JOIN artikel_kategorien ak ON ak.kategorie_id = k.id
            LEFT JOIN artikel vater ON vater.id = ak.artikel_id AND vater.aktiv = 1
            LEFT JOIN artikel a ON a.aktiv = 1 AND (
                (a.id = vater.id AND vater.ist_vater = 0)   -- NORMAL direkt
                OR
                (a.vaterartikel_id = vater.id)               -- KIND-Kinder
            )
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

    public function updateArtikelKategoriezuweisungen(int $artikelId, array $kategorieIds): void
    {
        // Alle bestehenden Zuweisungen löschen
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("DELETE FROM artikel_kategorien WHERE artikel_id = :artikel_id");
            $stmt->execute(['artikel_id' => $artikelId]);

            // Neue Zuweisungen einfügen
            $stmt = $this->db->prepare("INSERT INTO artikel_kategorien (artikel_id, kategorie_id) VALUES (:artikel_id, :kategorie_id)");
            foreach ($kategorieIds as $kategorieId) {
                $stmt->execute([
                    'artikel_id' => $artikelId,
                    'kategorie_id' => $kategorieId
                ]);
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e; // Fehler weiterwerfen oder entsprechend behandeln
        }
    }

    public function insert(string $name, ?int $parentId = null): int
    {
        $stmt = $this->db->prepare("INSERT INTO kategorien (name, parent_id) VALUES (:name, :parent_id)");
        $stmt->execute(['name' => $name, 'parent_id' => $parentId]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT id, parent_id, name, sortierung FROM kategorien WHERE id = :id");
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

    public function update(int $id, string $name, ?int $parentId): bool
    {
        $stmt = $this->db->prepare("UPDATE kategorien SET name = :name, parent_id = :parent_id WHERE id = :id");
        return $stmt->execute(['name' => $name, 'parent_id' => $parentId, 'id' => $id]);
    }

    public function findAlleKinderIds(int $id): array
    {
        // Rekursiv alle Nachkommen-IDs per CTE holen
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
