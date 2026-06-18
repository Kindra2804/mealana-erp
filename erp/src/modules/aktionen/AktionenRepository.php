<?php

require_once __DIR__ . '/../../core/Database.php';

class AktionenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Aktionen CRUD ─────────────────────────────────────────────────

    public function findAll(): array
    {
        $aktionen = $this->db->query("
            SELECT a.id, a.name, a.beschreibung, a.gestartet, a.erstellt_am
            FROM aktionen a
            ORDER BY a.erstellt_am DESC
        ")->fetchAll();

        if (empty($aktionen)) return [];

        $ids = array_column($aktionen, 'id');
        $pl  = implode(',', array_fill(0, count($ids), '?'));

        $kategorien = $this->db->prepare("
            SELECT ak.aktion_id, ak.id AS ak_id, k.name AS kat_name,
                   ak.gueltig_ab, ak.gueltig_bis
            FROM aktionen_kategorien ak
            JOIN kategorien k ON k.id = ak.kategorie_id
            WHERE ak.aktion_id IN ($pl)
            ORDER BY ak.gueltig_ab ASC
        ");
        $kategorien->execute($ids);
        $katRows = $kategorien->fetchAll();

        $katByAktion = [];
        foreach ($katRows as $row) {
            $katByAktion[$row['aktion_id']][] = $row;
        }

        $heute = date('Y-m-d');
        foreach ($aktionen as &$a) {
            $a['kategorien'] = $katByAktion[$a['id']] ?? [];
            $a['status']     = $this->berechneStatus($a, $a['kategorien'], $heute);
        }
        unset($a);

        return $aktionen;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT id, name, beschreibung, gestartet, erstellt_am
            FROM aktionen WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        $aktion = $stmt->fetch();
        if (!$aktion) return false;

        $aktion['kategorien'] = $this->getKategorien($id);
        $heute = date('Y-m-d');
        $aktion['status'] = $this->berechneStatus($aktion, $aktion['kategorien'], $heute);
        return $aktion;
    }

    public function insert(string $name, ?string $beschreibung): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO aktionen (name, beschreibung) VALUES (:name, :beschreibung)
        ");
        $stmt->execute(['name' => $name, 'beschreibung' => $beschreibung]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $name, ?string $beschreibung): bool
    {
        $stmt = $this->db->prepare("
            UPDATE aktionen SET name = :name, beschreibung = :beschreibung WHERE id = :id
        ");
        return $stmt->execute(['name' => $name, 'beschreibung' => $beschreibung, 'id' => $id]);
    }

    public function setGestartet(int $id, bool $gestartet): bool
    {
        $stmt = $this->db->prepare("UPDATE aktionen SET gestartet = :g WHERE id = :id");
        return $stmt->execute(['g' => $gestartet ? 1 : 0, 'id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM aktionen WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    // ── Kategorie-Zuweisungen ─────────────────────────────────────────

    public function getKategorien(int $aktionId): array
    {
        $stmt = $this->db->prepare("
            SELECT ak.id AS ak_id, ak.kategorie_id, k.name AS kat_name,
                   ak.gueltig_ab, ak.gueltig_bis
            FROM aktionen_kategorien ak
            JOIN kategorien k ON k.id = ak.kategorie_id
            WHERE ak.aktion_id = :id
            ORDER BY ak.gueltig_ab ASC
        ");
        $stmt->execute(['id' => $aktionId]);
        return $stmt->fetchAll();
    }

    public function getAktionsKategorienFuerAuswahl(): array
    {
        $stmt = $this->db->query("
            SELECT id, name FROM kategorien
            WHERE ist_aktions_kategorie = 1 AND aktiv = 1
            ORDER BY name ASC
        ");
        return $stmt->fetchAll();
    }

    public function hatZeitlicheUeberschneidung(int $aktionId, int $kategorieId, string $ab, string $bis, ?int $ausnahmeAkId = null): bool
    {
        $sql = "
            SELECT COUNT(*) FROM aktionen_kategorien
            WHERE kategorie_id = :kat_id
              AND aktion_id != :aktion_id
              AND gueltig_ab <= :bis
              AND gueltig_bis >= :ab
        ";
        if ($ausnahmeAkId !== null) {
            $sql .= " AND id != :ausnahme";
        }
        $stmt = $this->db->prepare($sql);
        $params = ['kat_id' => $kategorieId, 'aktion_id' => $aktionId, 'ab' => $ab, 'bis' => $bis];
        if ($ausnahmeAkId !== null) $params['ausnahme'] = $ausnahmeAkId;
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function addKategorie(int $aktionId, int $kategorieId, string $ab, string $bis): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO aktionen_kategorien (aktion_id, kategorie_id, gueltig_ab, gueltig_bis)
            VALUES (:aktion_id, :kat_id, :ab, :bis)
        ");
        $stmt->execute(['aktion_id' => $aktionId, 'kat_id' => $kategorieId, 'ab' => $ab, 'bis' => $bis]);
        return (int) $this->db->lastInsertId();
    }

    public function removeKategorie(int $akId): bool
    {
        // Vor dem Löschen: Aktionspreise für alle Artikel dieser Kategorie bereinigen
        $row = $this->db->prepare("SELECT aktion_id, kategorie_id FROM aktionen_kategorien WHERE id = :id");
        $row->execute(['id' => $akId]);
        $ak = $row->fetch();

        if ($ak) {
            $artStmt = $this->db->prepare("SELECT artikel_id FROM artikel_kategorien WHERE kategorie_id = :kid");
            $artStmt->execute(['kid' => $ak['kategorie_id']]);
            $artikelIds = $artStmt->fetchAll(\PDO::FETCH_COLUMN);

            if (!empty($artikelIds)) {
                $pl = implode(',', array_fill(0, count($artikelIds), '?'));
                $this->db->prepare("
                    DELETE FROM aktionen_artikel_preise
                    WHERE aktion_id = ? AND artikel_id IN ($pl)
                ")->execute(array_merge([$ak['aktion_id']], $artikelIds));
            }
        }

        $stmt = $this->db->prepare("DELETE FROM aktionen_kategorien WHERE id = :id");
        return $stmt->execute(['id' => $akId]);
    }

    // ── Preiseingabe ──────────────────────────────────────────────────

    public function getVaeterFuerKategorie(int $kategorieId): array
    {
        $stmt = $this->db->prepare("
            SELECT a.id, a.artikelnummer, a.name, a.steuerklasse_id, s.satz AS mwst_satz
            FROM artikel a
            JOIN artikel_kategorien ak ON ak.artikel_id = a.id
            LEFT JOIN steuerklassen s ON s.id = a.steuerklasse_id
            WHERE ak.kategorie_id = :kat_id
              AND a.vaterartikel_id IS NULL
              AND a.zustand_vater_id IS NULL
              AND a.aktiv = 1
            ORDER BY a.name ASC
        ");
        $stmt->execute(['kat_id' => $kategorieId]);
        return $stmt->fetchAll();
    }

    public function getSubAchsenFuerArtikel(array $artikelIds): array
    {
        if (empty($artikelIds)) return [];
        $pl   = implode(',', array_fill(0, count($artikelIds), '?'));
        $stmt = $this->db->prepare("
            SELECT aa.artikel_id, va.id AS achse_id, va.name AS achse_name
            FROM artikel_achsen aa
            JOIN varianten_achsen va ON va.id = aa.achse_id
            WHERE aa.artikel_id IN ($pl)
              AND va.abhaengig_von_achse_id IS NOT NULL
            ORDER BY aa.artikel_id, va.name ASC
        ");
        $stmt->execute($artikelIds);
        return $stmt->fetchAll();
    }

    public function getExistingPreise(int $aktionId, int $kgId): array
    {
        $stmt = $this->db->prepare("
            SELECT artikel_id, sub_achse_id, brutto_vk, netto_vk
            FROM aktionen_artikel_preise
            WHERE aktion_id = :aktion_id AND kundengruppen_id = :kg_id
        ");
        $stmt->execute(['aktion_id' => $aktionId, 'kg_id' => $kgId]);
        $rows = $stmt->fetchAll();

        // Index: "artikel_id:sub_achse_id" (sub_achse_id kann NULL sein → 0 als Key)
        $index = [];
        foreach ($rows as $r) {
            $key = $r['artikel_id'] . ':' . ($r['sub_achse_id'] ?? '0');
            $index[$key] = $r;
        }
        return $index;
    }

    public function upsertPreis(int $aktionId, int $artikelId, ?int $subAchseId, int $kgId, float $bruttoVk, float $nettoVk): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO aktionen_artikel_preise
                (aktion_id, artikel_id, sub_achse_id, kundengruppen_id, brutto_vk, netto_vk)
            VALUES (:aktion_id, :artikel_id, :sub_achse_id, :kg_id, :brutto, :netto)
            ON DUPLICATE KEY UPDATE brutto_vk = VALUES(brutto_vk), netto_vk = VALUES(netto_vk)
        ");
        $stmt->execute([
            'aktion_id'   => $aktionId,
            'artikel_id'  => $artikelId,
            'sub_achse_id' => $subAchseId,
            'kg_id'       => $kgId,
            'brutto'      => $bruttoVk,
            'netto'       => $nettoVk,
        ]);
    }

    public function deletePreis(int $aktionId, int $artikelId, ?int $subAchseId, int $kgId): void
    {
        if ($subAchseId === null) {
            $stmt = $this->db->prepare("
                DELETE FROM aktionen_artikel_preise
                WHERE aktion_id=:aid AND artikel_id=:artid AND sub_achse_id IS NULL AND kundengruppen_id=:kgid
            ");
            $stmt->execute(['aid' => $aktionId, 'artid' => $artikelId, 'kgid' => $kgId]);
        } else {
            $stmt = $this->db->prepare("
                DELETE FROM aktionen_artikel_preise
                WHERE aktion_id=:aid AND artikel_id=:artid AND sub_achse_id=:said AND kundengruppen_id=:kgid
            ");
            $stmt->execute(['aid' => $aktionId, 'artid' => $artikelId, 'said' => $subAchseId, 'kgid' => $kgId]);
        }
    }

    public function getAlleKundengruppen(): array
    {
        return $this->db->query("SELECT id, name, ist_standard FROM kundengruppen ORDER BY id")->fetchAll();
    }

    public function getNormalePreise(array $artikelIds, int $kgId): array
    {
        if (empty($artikelIds)) return [];
        $pl   = implode(',', array_fill(0, count($artikelIds), '?'));
        $stmt = $this->db->prepare("
            SELECT artikel_id, brutto_vk
            FROM artikel_preise
            WHERE artikel_id IN ($pl) AND kundengruppen_id = ?
        ");
        $stmt->execute(array_merge($artikelIds, [$kgId]));
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    // ── Hilfsmethoden ─────────────────────────────────────────────────

    private function berechneStatus(array $aktion, array $kategorien, string $heute): string
    {
        if (!$aktion['gestartet']) return 'entwurf';
        if (empty($kategorien))    return 'entwurf';

        $irgendwieAktiv   = false;
        $alleAbgelaufen   = true;
        foreach ($kategorien as $k) {
            if ($k['gueltig_bis'] >= $heute) $alleAbgelaufen = false;
            if ($k['gueltig_ab'] <= $heute && $k['gueltig_bis'] >= $heute) $irgendwieAktiv = true;
        }

        if ($irgendwieAktiv) return 'aktiv';
        if ($alleAbgelaufen) return 'abgelaufen';
        return 'geplant';
    }
}
