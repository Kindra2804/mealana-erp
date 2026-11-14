<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';

/**
 * MengenAktionService – Verwaltung der Mengen-Aktionen (Migration 241)
 *   "X Stück für Y €" (x_fuer_y) und "Kauf X, zahl Y" (kauf_x_zahl_y)
 *
 * Artikel einer Aktion = Artikel ihrer Kategorien (inkl. Unterkategorien) + einzeln zugeordnete,
 * jeweils mit allen Kindern. Die Preisberechnung (Pakete bilden, Rabatt gleichmäßig verteilen)
 * kommt in den Rechenkern (Schritt 2).
 * Seite: public/aktionen/mengen.php
 */
class MengenAktionService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Alle Aktionen mit Status, Kategorien, Shops und Artikelzahl (für die Liste) */
    public function alle(): array
    {
        $aktionen = $this->db->query("SELECT * FROM mengen_aktionen ORDER BY gueltig_ab DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($aktionen as &$a) $a = $this->ergaenzen($a);
        return $aktionen;
    }

    public function finde(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM mengen_aktionen WHERE id = ?");
        $st->execute([$id]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a) return null;
        $a = $this->ergaenzen($a);
        $st = $this->db->prepare("
            SELECT a.id, a.artikelnummer, a.name, a.aktiv,
                   (SELECT COUNT(*) FROM artikel k WHERE k.vaterartikel_id = a.id) AS kinder
            FROM mengen_aktion_artikel ma JOIN artikel a ON a.id = ma.artikel_id
            WHERE ma.aktion_id = ? ORDER BY a.artikelnummer");
        $st->execute([$id]);
        $a['artikel'] = $st->fetchAll(PDO::FETCH_ASSOC);
        return $a;
    }

    /**
     * Anlegen oder Ändern. $d: id?, name, art, menge, zahl_menge, paketpreis, badge_text,
     * gueltig_ab, gueltig_bis, aktiv, an_kasse, kategorie_ids[], shop_ids[]
     * @return int id
     */
    public function speichern(array $d): int
    {
        $id    = (int) ($d['id'] ?? 0);
        $name  = trim((string) ($d['name'] ?? ''));
        $art   = (string) ($d['art'] ?? '');
        $menge = (int) ($d['menge'] ?? 0);
        $zahl  = $art === 'kauf_x_zahl_y' ? (int) ($d['zahl_menge'] ?? 0) : null;
        $preis = $art === 'x_fuer_y' ? round((float) str_replace(',', '.', (string) ($d['paketpreis'] ?? '')), 2) : null;
        $ab    = (string) ($d['gueltig_ab'] ?? '');
        $bis   = trim((string) ($d['gueltig_bis'] ?? '')) ?: null;

        if ($name === '') throw new RuntimeException('Bitte einen Namen eingeben');
        if (!in_array($art, ['x_fuer_y', 'kauf_x_zahl_y'], true)) throw new RuntimeException('Bitte die Art wählen');
        if ($menge < 2) throw new RuntimeException('Ein Paket braucht mindestens 2 Stück');
        if ($art === 'kauf_x_zahl_y' && ($zahl < 1 || $zahl >= $menge)) throw new RuntimeException('„zahl Y“ muss kleiner als „Kauf X“ sein (z.B. 3 für 2)');
        if ($art === 'x_fuer_y' && $preis <= 0) throw new RuntimeException('Bitte den Paketpreis eingeben');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ab)) throw new RuntimeException('Bitte „gültig ab“ eingeben');
        if ($bis !== null && $bis < $ab) throw new RuntimeException('„gültig bis“ liegt vor „gültig ab“');

        $badge = trim((string) ($d['badge_text'] ?? '')) ?: self::badgeVorschlag($art, $menge, $zahl, $preis);
        $werte = [$name, $art, $menge, $zahl, $preis, mb_substr($badge, 0, 40), $ab, $bis,
                  empty($d['aktiv']) ? 0 : 1, empty($d['an_kasse']) ? 0 : 1];

        $this->db->beginTransaction();
        try {
            if ($id) {
                $st = $this->db->prepare("UPDATE mengen_aktionen SET name=?, art=?, menge=?, zahl_menge=?, paketpreis=?, badge_text=?,
                                          gueltig_ab=?, gueltig_bis=?, aktiv=?, an_kasse=? WHERE id=?");
                $st->execute([...$werte, $id]);
            } else {
                $st = $this->db->prepare("INSERT INTO mengen_aktionen (name, art, menge, zahl_menge, paketpreis, badge_text,
                                          gueltig_ab, gueltig_bis, aktiv, an_kasse) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $st->execute($werte);
                $id = (int) $this->db->lastInsertId();
            }
            $this->zuordnungSetzen('mengen_aktion_kategorien', 'kategorie_id', $id, (array) ($d['kategorie_ids'] ?? []));
            $this->zuordnungSetzen('mengen_aktion_shops', 'shop_id', $id, (array) ($d['shop_ids'] ?? []));
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        Logger::log('mengen_aktion.speichern', 'mengen_aktionen', $id, ['name' => $name, 'badge' => $badge]);
        return $id;
    }

    public function loeschen(int $id): void
    {
        $this->db->prepare("DELETE FROM mengen_aktionen WHERE id = ?")->execute([$id]);   // Zuordnungen per CASCADE
        Logger::log('mengen_aktion.loeschen', 'mengen_aktionen', $id);
    }

    /** Massenaktion Artikelliste: Artikel einzeln zuordnen. @return int neu zugeordnet */
    public function artikelHinzufuegen(int $aktionId, array $artikelIds): int
    {
        if (!$this->finde($aktionId)) throw new RuntimeException('Mengen-Aktion nicht gefunden');
        $st = $this->db->prepare("INSERT IGNORE INTO mengen_aktion_artikel (aktion_id, artikel_id) VALUES (?, ?)");
        $n = 0;
        foreach (array_unique(array_filter(array_map('intval', $artikelIds))) as $aid) {
            $st->execute([$aktionId, $aid]);
            $n += $st->rowCount();
        }
        Logger::log('mengen_aktion.artikel_hinzu', 'mengen_aktionen', $aktionId, ['artikel' => $n]);
        return $n;
    }

    public function artikelEntfernen(int $aktionId, int $artikelId): void
    {
        $this->db->prepare("DELETE FROM mengen_aktion_artikel WHERE aktion_id = ? AND artikel_id = ?")->execute([$aktionId, $artikelId]);
    }

    /**
     * Alle Artikel-IDs einer Aktion: Kategorien inkl. Unterkategorien + einzeln zugeordnete, jeweils mit Kindern.
     * @return int[]
     */
    public function artikelIds(int $aktionId): array
    {
        $st = $this->db->prepare("
            WITH RECURSIVE kats AS (
                SELECT kategorie_id AS id FROM mengen_aktion_kategorien WHERE aktion_id = :a1
                UNION
                SELECT k.id FROM kategorien k JOIN kats ON k.parent_id = kats.id
            ),
            basis AS (
                SELECT ak.artikel_id AS id FROM artikel_kategorien ak JOIN kats ON kats.id = ak.kategorie_id
                UNION
                SELECT artikel_id FROM mengen_aktion_artikel WHERE aktion_id = :a2
            )
            SELECT id FROM basis
            UNION
            SELECT a.id FROM artikel a JOIN basis ON a.vaterartikel_id = basis.id");
        $st->execute(['a1' => $aktionId, 'a2' => $aktionId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Kategorien flach mit Einrückungstiefe, in Baum-Reihenfolge (für die Auswahl) */
    public function kategorienFlach(): array
    {
        $alle = $this->db->query("SELECT id, parent_id, name FROM kategorien WHERE aktiv = 1 ORDER BY sortierung, name")->fetchAll(PDO::FETCH_ASSOC);
        $kinder = [];
        foreach ($alle as $k) $kinder[(int) $k['parent_id']][] = $k;
        $erg = [];
        $lauf = function (int $parent, int $tiefe) use (&$lauf, &$erg, $kinder) {
            foreach ($kinder[$parent] ?? [] as $k) {
                $erg[] = ['id' => (int) $k['id'], 'name' => $k['name'], 'tiefe' => $tiefe];
                $lauf((int) $k['id'], $tiefe + 1);
            }
        };
        $lauf(0, 0);
        return $erg;
    }

    public function shops(): array
    {
        return $this->db->query("SELECT id, name, nr_kuerzel FROM shops WHERE ist_aktiv = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Vorschlag für den Badge-Text: "4 für 12 €" bzw. "3 für 2" */
    public static function badgeVorschlag(string $art, int $menge, ?int $zahl, ?float $preis): string
    {
        if ($art === 'kauf_x_zahl_y') return $menge . ' für ' . $zahl;
        $p = fmod((float) $preis, 1.0) == 0.0 ? number_format((float) $preis, 0, ',', '.') : number_format((float) $preis, 2, ',', '.');
        return $menge . ' für ' . $p . ' €';
    }

    // ── intern ───────────────────────────────────────────────────────

    private function ergaenzen(array $a): array
    {
        $heute = date('Y-m-d');
        $a['status'] = !$a['aktiv'] ? 'pausiert'
            : ($a['gueltig_ab'] > $heute ? 'geplant'
            : ($a['gueltig_bis'] !== null && $a['gueltig_bis'] < $heute ? 'abgelaufen' : 'aktiv'));

        $st = $this->db->prepare("SELECT k.id, k.name FROM mengen_aktion_kategorien mk JOIN kategorien k ON k.id = mk.kategorie_id WHERE mk.aktion_id = ? ORDER BY k.name");
        $st->execute([$a['id']]);
        $a['kategorien'] = $st->fetchAll(PDO::FETCH_ASSOC);

        $st = $this->db->prepare("SELECT s.id, s.name, s.nr_kuerzel FROM mengen_aktion_shops ms JOIN shops s ON s.id = ms.shop_id WHERE ms.aktion_id = ? ORDER BY s.id");
        $st->execute([$a['id']]);
        $a['shops'] = $st->fetchAll(PDO::FETCH_ASSOC);

        $a['anzahl_artikel'] = count($this->artikelIds((int) $a['id']));
        return $a;
    }

    private function zuordnungSetzen(string $tabelle, string $spalte, int $aktionId, array $ids): void
    {
        $this->db->prepare("DELETE FROM $tabelle WHERE aktion_id = ?")->execute([$aktionId]);
        $st = $this->db->prepare("INSERT INTO $tabelle (aktion_id, $spalte) VALUES (?, ?)");
        foreach (array_unique(array_filter(array_map('intval', $ids))) as $x) $st->execute([$aktionId, $x]);
    }
}
