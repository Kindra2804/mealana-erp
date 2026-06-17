<?php
require_once __DIR__ . '/../../core/Database.php';

class MerkmaleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAllMitWerten(): array
    {
        $merkmale = $this->db->query("
            SELECT m.id, m.name, m.slug, m.datentyp, m.filterbar, m.mehrfach_auswahl, m.sort_order, m.aktiv,
                   GROUP_CONCAT(mat.artikeltyp_id ORDER BY mat.artikeltyp_id) AS artikeltyp_ids
            FROM merkmale m
            LEFT JOIN merkmal_artikeltypen mat ON m.id = mat.merkmal_id
            WHERE m.aktiv = 1
            GROUP BY m.id
            ORDER BY m.sort_order, m.name
        ")->fetchAll();

        foreach ($merkmale as &$m) {
            $m['artikeltyp_ids'] = $m['artikeltyp_ids']
                ? array_map('intval', explode(',', $m['artikeltyp_ids']))
                : [];
            $stmt = $this->db->prepare("SELECT id, wert, sort_order FROM merkmal_werte WHERE merkmal_id = :mid ORDER BY sort_order, wert");
            $stmt->execute(['mid' => $m['id']]);
            $m['werte'] = $stmt->fetchAll();
        }
        return $merkmale;
    }

    public function findFuerArtikeltyp(?int $artikeltypId): array
    {
        if ($artikeltypId === null) {
            $stmt = $this->db->query("
                SELECT m.id, m.name, m.mehrfach_auswahl, m.filterbar
                FROM merkmale m
                WHERE m.aktiv = 1
                  AND NOT EXISTS (SELECT 1 FROM merkmal_artikeltypen WHERE merkmal_id = m.id)
                ORDER BY m.sort_order, m.name
            ");
        } else {
            $stmt = $this->db->prepare("
                SELECT m.id, m.name, m.mehrfach_auswahl, m.filterbar
                FROM merkmale m
                WHERE m.aktiv = 1
                  AND (
                      NOT EXISTS (SELECT 1 FROM merkmal_artikeltypen WHERE merkmal_id = m.id)
                      OR EXISTS (SELECT 1 FROM merkmal_artikeltypen WHERE merkmal_id = m.id AND artikeltyp_id = :atid)
                  )
                ORDER BY m.sort_order, m.name
            ");
            $stmt->execute(['atid' => $artikeltypId]);
        }
        $merkmale = $stmt->fetchAll();

        foreach ($merkmale as &$m) {
            $stmt2 = $this->db->prepare("SELECT id, wert FROM merkmal_werte WHERE merkmal_id = :mid ORDER BY sort_order, wert");
            $stmt2->execute(['mid' => $m['id']]);
            $m['werte'] = $stmt2->fetchAll();
        }
        return $merkmale;
    }

    public function findByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT am.merkmal_id, am.merkmal_wert_id, mw.wert, m.name AS merkmal_name, m.mehrfach_auswahl
            FROM artikel_merkmale am
            JOIN merkmal_werte mw ON am.merkmal_wert_id = mw.id
            JOIN merkmale m ON am.merkmal_id = m.id
            WHERE am.artikel_id = :aid
        ");
        $stmt->execute(['aid' => $artikelId]);
        return $stmt->fetchAll();
    }

    public function insertMerkmal(string $name, string $slug, bool $mehrfach, bool $filterbar): int
    {
        $maxSort = (int)$this->db->query("SELECT COALESCE(MAX(sort_order),0) FROM merkmale")->fetchColumn();
        $stmt = $this->db->prepare("INSERT INTO merkmale (name, slug, mehrfach_auswahl, filterbar, aktiv, sort_order, datentyp, einheit) VALUES (:n, :s, :m, :f, 1, :so, 'text', '')");
        $stmt->execute(['n' => $name, 's' => $slug, 'm' => (int)$mehrfach, 'f' => (int)$filterbar, 'so' => $maxSort + 10]);
        return (int)$this->db->lastInsertId();
    }

    public function updateMerkmal(int $id, string $name, string $slug, bool $mehrfach, bool $filterbar): void
    {
        $stmt = $this->db->prepare("UPDATE merkmale SET name=:n, slug=:s, mehrfach_auswahl=:m, filterbar=:f WHERE id=:id");
        $stmt->execute(['n' => $name, 's' => $slug, 'm' => (int)$mehrfach, 'f' => (int)$filterbar, 'id' => $id]);
    }

    public function deleteMerkmal(int $id): void
    {
        $this->db->prepare("UPDATE merkmale SET aktiv=0 WHERE id=:id")->execute(['id' => $id]);
    }

    public function setArtikeltypen(int $merkmalId, array $artikeltypIds): void
    {
        $this->db->prepare("DELETE FROM merkmal_artikeltypen WHERE merkmal_id=:mid")->execute(['mid' => $merkmalId]);
        $stmt = $this->db->prepare("INSERT INTO merkmal_artikeltypen (merkmal_id, artikeltyp_id) VALUES (:mid, :atid)");
        foreach ($artikeltypIds as $atid) {
            $stmt->execute(['mid' => $merkmalId, 'atid' => (int)$atid]);
        }
    }

    public function insertWert(int $merkmalId, string $wert): int
    {
        $stmtMax = $this->db->prepare("SELECT COALESCE(MAX(sort_order),0) FROM merkmal_werte WHERE merkmal_id=:mid");
        $stmtMax->execute(['mid' => $merkmalId]);
        $maxSort = (int)$stmtMax->fetchColumn();
        $stmt = $this->db->prepare("INSERT INTO merkmal_werte (merkmal_id, wert, sort_order) VALUES (:mid, :wert, :so)");
        $stmt->execute(['mid' => $merkmalId, 'wert' => trim($wert), 'so' => $maxSort + 10]);
        return (int)$this->db->lastInsertId();
    }

    public function updateWert(int $id, string $wert): void
    {
        $this->db->prepare("UPDATE merkmal_werte SET wert=:wert WHERE id=:id")->execute(['wert' => trim($wert), 'id' => $id]);
    }

    public function deleteWert(int $id): void
    {
        $this->db->prepare("DELETE FROM merkmal_werte WHERE id=:id")->execute(['id' => $id]);
    }

    public function sortMerkmal(int $id, string $richtung): void
    {
        $this->tauschSort('merkmale', $id, $richtung, null, null);
    }

    public function sortWert(int $id, string $richtung): void
    {
        $stmt = $this->db->prepare("SELECT merkmal_id FROM merkmal_werte WHERE id=:id");
        $stmt->execute(['id' => $id]);
        $merkmalId = (int)$stmt->fetchColumn();
        $this->tauschSort('merkmal_werte', $id, $richtung, 'merkmal_id', $merkmalId);
    }

    private function tauschSort(string $tabelle, int $id, string $richtung, ?string $filterSpalte, ?int $filterWert): void
    {
        $where = $filterSpalte ? "AND $filterSpalte = $filterWert" : '';
        $aktivFilter = ($tabelle === 'merkmale') ? 'AND aktiv = 1' : '';
        $aktuell = $this->db->query("SELECT sort_order FROM $tabelle WHERE id=$id")->fetchColumn();

        if ($richtung === 'hoch') {
            $nachbar = $this->db->query("SELECT id, sort_order FROM $tabelle WHERE sort_order < $aktuell $where $aktivFilter ORDER BY sort_order DESC LIMIT 1")->fetch();
        } else {
            $nachbar = $this->db->query("SELECT id, sort_order FROM $tabelle WHERE sort_order > $aktuell $where $aktivFilter ORDER BY sort_order ASC LIMIT 1")->fetch();
        }
        if (!$nachbar) return;

        $this->db->prepare("UPDATE $tabelle SET sort_order=:so WHERE id=:id")->execute(['so' => $nachbar['sort_order'], 'id' => $id]);
        $this->db->prepare("UPDATE $tabelle SET sort_order=:so WHERE id=:id")->execute(['so' => $aktuell, 'id' => $nachbar['id']]);
    }
}
