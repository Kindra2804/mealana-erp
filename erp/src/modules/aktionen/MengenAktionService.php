<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';

/**
 * MengenAktionService – Verwaltung der Mengen-Aktionen (Migration 241, 242)
 *   "X Stück für Y €" (x_fuer_y), "Kauf X, zahl Y" (kauf_x_zahl_y) und "Paketpreise" (mehrere Paketgrößen)
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

        // Paketpreise: Zeilen Menge/Preis (leere ignorieren); menge = kleinste Paketgröße
        $pakete = [];
        if ($art === 'paketpreise') {
            foreach ((array) ($d['paket_menge'] ?? []) as $i => $m) {
                $m = (int) $m;
                $p = round((float) str_replace(',', '.', (string) ($d['paket_preis'][$i] ?? '')), 2);
                if ($m === 0 && $p == 0) continue;
                if ($m < 2 || $p <= 0) throw new RuntimeException('Jede Paketzeile braucht mindestens 2 Stück und einen Preis');
                if (isset($pakete[$m])) throw new RuntimeException("Paketgröße $m kommt doppelt vor");
                $pakete[$m] = $p;
            }
            if (!$pakete) throw new RuntimeException('Bitte mindestens eine Paketgröße eingeben');
            ksort($pakete);
            $menge = array_key_first($pakete);
        }

        if ($name === '') throw new RuntimeException('Bitte einen Namen eingeben');
        if (!in_array($art, ['x_fuer_y', 'kauf_x_zahl_y', 'paketpreise'], true)) throw new RuntimeException('Bitte die Art wählen');
        if ($menge < 2) throw new RuntimeException('Ein Paket braucht mindestens 2 Stück');
        if ($art === 'kauf_x_zahl_y' && ($zahl < 1 || $zahl >= $menge)) throw new RuntimeException('„zahl Y“ muss kleiner als „Kauf X“ sein (z.B. 3 für 2)');
        if ($art === 'x_fuer_y' && $preis <= 0) throw new RuntimeException('Bitte den Paketpreis eingeben');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ab)) throw new RuntimeException('Bitte „gültig ab“ eingeben');
        if ($bis !== null && $bis < $ab) throw new RuntimeException('„gültig bis“ liegt vor „gültig ab“');

        $badge = trim((string) ($d['badge_text'] ?? '')) ?: self::badgeVorschlag($art, $menge, $zahl, $preis, $pakete);
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
            $this->db->prepare("DELETE FROM mengen_aktion_pakete WHERE aktion_id = ?")->execute([$id]);
            $st = $this->db->prepare("INSERT INTO mengen_aktion_pakete (aktion_id, menge, preis) VALUES (?, ?, ?)");
            foreach ($pakete as $m => $p) $st->execute([$id, $m, $p]);
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

    /**
     * Heute gültige Aktionen für einen Kanal, im Format für MengenAktionRechner::berechne()
     * @param string   $kanal  'kasse' oder 'shop'
     * @param int|null $shopId bei 'shop' Pflicht
     */
    public function aktiveRegeln(string $kanal, ?int $shopId = null): array
    {
        $sql = "SELECT id, art, menge, zahl_menge, paketpreis, badge_text FROM mengen_aktionen m
                WHERE aktiv = 1 AND gueltig_ab <= CURDATE() AND (gueltig_bis IS NULL OR gueltig_bis >= CURDATE())";
        $params = [];
        if ($kanal === 'kasse') {
            $sql .= " AND an_kasse = 1";
        } else {
            $sql .= " AND EXISTS (SELECT 1 FROM mengen_aktion_shops ms WHERE ms.aktion_id = m.id AND ms.shop_id = ?)";
            $params[] = (int) $shopId;
        }
        $st = $this->db->prepare($sql . " ORDER BY id");
        $st->execute($params);
        $regeln = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $a['id'] = (int) $a['id'];
            $a['menge'] = (int) $a['menge'];
            $a['zahl_menge'] = $a['zahl_menge'] === null ? null : (int) $a['zahl_menge'];
            $a['paketpreis'] = $a['paketpreis'] === null ? null : (float) $a['paketpreis'];
            $a['artikel_ids'] = $this->artikelIds($a['id']);
            $pk = $this->db->prepare("SELECT menge, preis FROM mengen_aktion_pakete WHERE aktion_id = ? ORDER BY menge");
            $pk->execute([$a['id']]);
            $a['pakete'] = array_map(fn($p) => ['menge' => (int) $p['menge'], 'preis' => (float) $p['preis']], $pk->fetchAll(PDO::FETCH_ASSOC));
            $regeln[] = $a;
        }
        return $regeln;
    }

    /**
     * Regeln für die Offline-/Messe-Kasse (an_kasse): nicht abgelaufen, mit Zeitraum (das Gerät prüft
     * das Datum selbst, eine Messe dauert oft mehrere Tage), Artikel auf die mitgenommenen beschränkt.
     */
    public function regelnFuerMesse(array $artikelIds): array
    {
        $mit = array_flip(array_map('intval', $artikelIds));
        $st = $this->db->query("SELECT id, art, menge, zahl_menge, paketpreis, badge_text, gueltig_ab, gueltig_bis
                                FROM mengen_aktionen
                                WHERE aktiv = 1 AND an_kasse = 1 AND (gueltig_bis IS NULL OR gueltig_bis >= CURDATE())
                                ORDER BY id");
        $regeln = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $ids = array_values(array_filter($this->artikelIds((int) $a['id']), fn($id) => isset($mit[(int) $id])));
            if (!$ids) continue;
            $pk = $this->db->prepare("SELECT menge, preis FROM mengen_aktion_pakete WHERE aktion_id = ? ORDER BY menge");
            $pk->execute([$a['id']]);
            $regeln[] = [
                'id' => (int) $a['id'], 'art' => $a['art'], 'menge' => (int) $a['menge'],
                'zahl_menge' => $a['zahl_menge'] === null ? null : (int) $a['zahl_menge'],
                'paketpreis' => $a['paketpreis'] === null ? null : (float) $a['paketpreis'],
                'badge_text' => $a['badge_text'], 'gueltig_ab' => $a['gueltig_ab'], 'gueltig_bis' => $a['gueltig_bis'],
                'artikel_ids' => array_map('intval', $ids),
                'pakete' => array_map(fn($p) => ['menge' => (int) $p['menge'], 'preis' => (float) $p['preis']], $pk->fetchAll(PDO::FETCH_ASSOC)),
            ];
        }
        return $regeln;
    }

    // ── Übertragung an die Shops (NAHTLOS Connector ≥ 1.7.0, POST nahtlos/v1/mengen-aktionen) ──

    /**
     * Alle nicht abgelaufenen, aktiven Aktionen eines Shops mit den Woo-IDs ihrer Produkte/Variationen.
     * Den Zeitraum prüft der Shop selbst (von/bis).
     */
    public function shopPayload(int $shopId): array
    {
        $st = $this->db->prepare("
            SELECT m.* FROM mengen_aktionen m
            JOIN mengen_aktion_shops ms ON ms.aktion_id = m.id AND ms.shop_id = ?
            WHERE m.aktiv = 1 AND (m.gueltig_bis IS NULL OR m.gueltig_bis >= CURDATE())
            ORDER BY m.id");
        $st->execute([$shopId]);
        $aktionen = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $ids = $this->artikelIds((int) $a['id']);
            $woo = [];
            if ($ids) {
                $w = $this->db->prepare("SELECT DISTINCT CAST(external_id AS UNSIGNED) FROM artikel_shops
                                         WHERE shop_id = ? AND aktiv = 1 AND external_id IS NOT NULL AND external_id <> ''
                                           AND artikel_id IN (" . implode(',', $ids) . ")");
                $w->execute([$shopId]);
                $woo = array_map('intval', $w->fetchAll(PDO::FETCH_COLUMN));
                sort($woo);
            }
            $pk = $this->db->prepare("SELECT menge, preis FROM mengen_aktion_pakete WHERE aktion_id = ? ORDER BY menge");
            $pk->execute([$a['id']]);
            $aktionen[] = [
                'id'         => (int) $a['id'],
                'art'        => $a['art'],
                'menge'      => (int) $a['menge'],
                'zahl_menge' => $a['zahl_menge'] === null ? null : (int) $a['zahl_menge'],
                'paketpreis' => $a['paketpreis'] === null ? null : (float) $a['paketpreis'],
                'pakete'     => array_map(fn($p) => ['menge' => (int) $p['menge'], 'preis' => (float) $p['preis']], $pk->fetchAll(PDO::FETCH_ASSOC)),
                'badge_text' => $a['badge_text'],
                'von'        => $a['gueltig_ab'],
                'bis'        => $a['gueltig_bis'],
                'woo_ids'    => $woo,
            ];
        }
        return ['version' => 1, 'aktionen' => $aktionen];
    }

    /**
     * An einen Shop übertragen – nur wenn sich die Liste seit der letzten Übertragung geändert hat
     * (oder $erzwingen). @return array ['status' => 'uebertragen'|'unveraendert'|'fehler', 'fehler'?, 'aktionen'?]
     */
    public function uebertrageShop(array $shop, ?int $benutzerId = null, bool $erzwingen = false): array
    {
        $payload = $this->shopPayload((int) $shop['id']);
        $hash = md5(json_encode($payload));
        if (!$erzwingen && $hash === ($shop['mengen_hash'] ?? null)) return ['status' => 'unveraendert'];
        try {
            if (!$shop['wc_url'] || !$shop['wp_username'] || !$shop['wp_app_password']) {
                throw new RuntimeException('Für diesen Shop fehlt die WordPress-Anmeldung (Einstellungen → Kanäle).');
            }
            require_once __DIR__ . '/../shop/WooCommerceClient.php';
            $client = new WooCommerceClient($shop['wc_url'], (string) $shop['wc_key'], (string) $shop['wc_secret'], $shop['wp_username'], $shop['wp_app_password']);
            $client->connector('POST', 'mengen-aktionen', $payload);
        } catch (Throwable $e) {
            $fehler = mb_substr($e->getMessage(), 0, 500);
            // nur beim ersten Auftreten protokollieren, nicht bei jedem Cron-Lauf
            if ($fehler !== ($shop['mengen_fehler'] ?? null)) {
                Logger::log('mengen_aktion.uebertragen', 'shops', (int) $shop['id'], ['fehler' => $fehler], $benutzerId, 'error');
            }
            $this->db->prepare("UPDATE shops SET mengen_fehler = ? WHERE id = ?")->execute([$fehler, $shop['id']]);
            return ['status' => 'fehler', 'fehler' => $fehler];
        }
        $this->db->prepare("UPDATE shops SET mengen_hash = ?, mengen_uebertragen_am = NOW(), mengen_fehler = NULL WHERE id = ?")
                 ->execute([$hash, $shop['id']]);
        Logger::log('mengen_aktion.uebertragen', 'shops', (int) $shop['id'], ['aktionen' => count($payload['aktionen'])], $benutzerId);
        return ['status' => 'uebertragen', 'aktionen' => count($payload['aktionen'])];
    }

    /**
     * Alle aktiven, nicht pausierten Shops mit WordPress-Anmeldung (nach dem Speichern + im Cron).
     * @return array shop-name => Ergebnis
     */
    public function uebertrageAlle(?int $benutzerId = null, ?callable $log = null): array
    {
        $erg = [];
        $shops = $this->db->query("SELECT * FROM shops WHERE ist_aktiv = 1 AND sync_pausiert = 0
                                   AND wc_url IS NOT NULL AND wc_url <> '' AND wp_app_password IS NOT NULL AND wp_app_password <> ''")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($shops as $shop) {
            $r = $this->uebertrageShop($shop, $benutzerId);
            $erg[$shop['name']] = $r;
            if ($log && $r['status'] !== 'unveraendert') {
                $log("Mengen-Aktionen {$shop['name']}: " . ($r['status'] === 'uebertragen' ? "{$r['aktionen']} übertragen" : 'FEHLER ' . $r['fehler']));
            }
        }
        return $erg;
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
    public static function badgeVorschlag(string $art, int $menge, ?int $zahl, ?float $preis, array $pakete = []): string
    {
        if ($art === 'kauf_x_zahl_y') return $menge . ' für ' . $zahl;
        if ($art === 'paketpreise') return 'Mengenpreis ab ' . $menge . ' Stk';
        return $menge . ' für ' . self::euro((float) $preis) . ' €';
    }

    /** 12 → "12", 4.5 → "4,50" */
    public static function euro(float $v): string
    {
        return fmod($v, 1.0) == 0.0 ? number_format($v, 0, ',', '.') : number_format($v, 2, ',', '.');
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

        $st = $this->db->prepare("SELECT menge, preis FROM mengen_aktion_pakete WHERE aktion_id = ? ORDER BY menge");
        $st->execute([$a['id']]);
        $a['pakete'] = array_map(fn($p) => ['menge' => (int) $p['menge'], 'preis' => (float) $p['preis']], $st->fetchAll(PDO::FETCH_ASSOC));

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
