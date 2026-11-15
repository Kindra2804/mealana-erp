<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * Newsletter-Segmente (Schritt 5): welche bestätigten Empfänger eines Shops einen Newsletter bekommen.
 * Alle gesetzten Bedingungen müssen zutreffen (UND), innerhalb einer Liste reicht eine (ODER). Leer = egal.
 *
 *   kunde           '' | 'kunden' (hat ein Kundenkonto im ERP) | 'nicht_kunden'
 *   kundengruppen   [id, …]
 *   laender         ['AT', 'DE', …]   (irgendeine Adresse des Kunden)
 *   kategorien      [id, …]  gekauft aus diesen Kategorien (inkl. Unterkategorien) …
 *   hersteller      [id, …]  … bzw. von diesen Herstellern …
 *   kauf_monate     N        … in den letzten N Monaten (Standard 24)
 *   letzter_kauf    '' | 'innerhalb' | 'nicht_innerhalb' (hat schon gekauft, aber nicht in den letzten N Monaten)
 *   letzter_monate  N
 *   angemeldet_seit 'YYYY-MM-DD'
 *
 * Zuordnung Empfänger ↔ Kunde über die E-Mail (email_hash); wird vor dem Zählen/Versand nachgezogen.
 * Gezählt werden Aufträge aller Kanäle (Shop, Kasse mit Kunde, JTL-Archiv), ohne stornierte.
 */
class NewsletterSegmentService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function aktiv(): bool
    {
        return Database::getInstance()->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'newsletter_segmente_aktiv'")->fetchColumn() === '1';
    }

    /** Eingabe säubern; null = kein Segment (alle bestätigten Empfänger) */
    public function normalisieren($s): ?array
    {
        if (!is_array($s)) return null;
        $ids = fn($l) => array_values(array_unique(array_filter(array_map('intval', (array)$l))));
        $n = [
            'kunde'           => in_array($s['kunde'] ?? '', ['kunden', 'nicht_kunden'], true) ? $s['kunde'] : '',
            'kundengruppen'   => $ids($s['kundengruppen'] ?? []),
            'laender'         => array_values(array_unique(array_filter(array_map(fn($l) => strtoupper(trim((string)$l)),
                                    is_array($s['laender'] ?? null) ? $s['laender'] : explode(',', (string)($s['laender'] ?? ''))), fn($l) => preg_match('/^[A-Z]{2}$/', $l)))),
            'kategorien'      => $ids($s['kategorien'] ?? []),
            'hersteller'      => $ids($s['hersteller'] ?? []),
            'kauf_monate'     => max(1, min(240, (int)($s['kauf_monate'] ?? 24) ?: 24)),
            'letzter_kauf'    => in_array($s['letzter_kauf'] ?? '', ['innerhalb', 'nicht_innerhalb'], true) ? $s['letzter_kauf'] : '',
            'letzter_monate'  => max(1, min(240, (int)($s['letzter_monate'] ?? 12) ?: 12)),
            'angemeldet_seit' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($s['angemeldet_seit'] ?? '')) ? $s['angemeldet_seit'] : '',
        ];
        $leer = $n['kunde'] === '' && !$n['kundengruppen'] && !$n['laender'] && !$n['kategorien'] && !$n['hersteller']
             && $n['letzter_kauf'] === '' && $n['angemeldet_seit'] === '';
        return $leer ? null : $n;
    }

    /** SQL-Bedingung für newsletter_empfaenger AS e: [sql, params] */
    public function bedingung(?array $s): array
    {
        if (!$s) return ['1 = 1', []];
        $w = [];
        $p = [];
        $ohneStorno = "au.zahlungsstatus <> 'storniert' AND au.lieferstatus <> 'storniert'";
        $in = function (array $liste) use (&$p): string {
            foreach ($liste as $v) $p[] = $v;
            return implode(',', array_fill(0, count($liste), '?'));
        };

        if ($s['kunde'] === 'kunden')       $w[] = "e.kunden_id IS NOT NULL";
        if ($s['kunde'] === 'nicht_kunden') $w[] = "e.kunden_id IS NULL";
        if ($s['kundengruppen']) $w[] = "EXISTS (SELECT 1 FROM kunden k WHERE k.id = e.kunden_id AND k.kundengruppe_id IN (" . $in($s['kundengruppen']) . "))";
        if ($s['laender'])       $w[] = "EXISTS (SELECT 1 FROM kunden_adressen ka WHERE ka.kunde_id = e.kunden_id AND ka.land IN (" . $in($s['laender']) . "))";

        if ($s['kategorien'] || $s['hersteller']) {
            $art = [];
            if ($s['kategorien']) {
                $art[] = "EXISTS (SELECT 1 FROM artikel_kategorien ak WHERE ak.artikel_id IN (a.id, COALESCE(a.vaterartikel_id, a.id))
                                  AND ak.kategorie_id IN (" . $in($this->mitUnterkategorien($s['kategorien'])) . "))";
            }
            if ($s['hersteller']) {
                $art[] = "COALESCE(a.hersteller_id, v.hersteller_id) IN (" . $in($s['hersteller']) . ")";
            }
            $w[] = "EXISTS (SELECT 1 FROM auftraege au JOIN auftrag_positionen ap ON ap.auftrag_id = au.id
                            JOIN artikel a ON a.id = ap.artikel_id LEFT JOIN artikel v ON v.id = a.vaterartikel_id
                            WHERE au.kunden_id = e.kunden_id AND $ohneStorno
                              AND au.erstellt_am >= NOW() - INTERVAL " . (int)$s['kauf_monate'] . " MONTH AND " . implode(' AND ', $art) . ")";
        }

        if ($s['letzter_kauf'] !== '') {
            $fenster = "EXISTS (SELECT 1 FROM auftraege au WHERE au.kunden_id = e.kunden_id AND $ohneStorno
                                AND au.erstellt_am >= NOW() - INTERVAL " . (int)$s['letzter_monate'] . " MONTH)";
            $w[] = $s['letzter_kauf'] === 'innerhalb'
                ? $fenster
                : "EXISTS (SELECT 1 FROM auftraege au WHERE au.kunden_id = e.kunden_id AND $ohneStorno) AND NOT $fenster";
        }

        if ($s['angemeldet_seit'] !== '') {
            $w[] = "COALESCE(e.optin_am, e.eingetragen_am) >= ?";
            $p[] = $s['angemeldet_seit'];
        }
        return ['(' . implode(') AND (', $w) . ')', $p];
    }

    /** @return array{treffer:int, gesamt:int} bestätigte Empfänger des Shops im Segment / insgesamt */
    public function zaehlen(int $shopId, ?array $s): array
    {
        $this->kundenVerknuepfen($shopId);
        [$sql, $p] = $this->bedingung($s);
        $st = $this->db->prepare("SELECT COUNT(*) FROM newsletter_empfaenger e WHERE e.shop_id = ? AND e.status = 'aktiv' AND $sql");
        $st->execute(array_merge([$shopId], $p));
        $treffer = (int)$st->fetchColumn();
        $st = $this->db->prepare("SELECT COUNT(*) FROM newsletter_empfaenger WHERE shop_id = ? AND status = 'aktiv'");
        $st->execute([$shopId]);
        return ['treffer' => $treffer, 'gesamt' => (int)$st->fetchColumn()];
    }

    /** Empfänger ohne Kundenzuordnung mit inzwischen angelegten Kunden verbinden (gleiche E-Mail) */
    public function kundenVerknuepfen(int $shopId): int
    {
        $st = $this->db->prepare("UPDATE newsletter_empfaenger e JOIN kunden k ON k.email_hash = e.email_hash COLLATE utf8mb4_general_ci AND k.status <> 'geloescht'   -- Spalten haben verschiedene Sortierregeln
                                  SET e.kunden_id = k.id, e.geaendert_am = e.geaendert_am
                                  WHERE e.shop_id = ? AND e.kunden_id IS NULL");
        $st->execute([$shopId]);
        return $st->rowCount();
    }

    /** Lesbare Beschreibung, z.B. für die Versand-Rückfrage und die Liste */
    public function beschreibung(?array $s): string
    {
        if (!$s) return 'alle bestätigten Empfänger';
        $t = [];
        if ($s['kunde'] === 'kunden')       $t[] = 'nur Kunden';
        if ($s['kunde'] === 'nicht_kunden') $t[] = 'nur Nicht-Kunden';
        if ($s['kundengruppen']) $t[] = 'Kundengruppe ' . implode(' / ', $this->namen('kundengruppen', $s['kundengruppen']));
        if ($s['laender'])       $t[] = 'Land ' . implode(' / ', $s['laender']);
        $kauf = [];
        if ($s['kategorien']) $kauf[] = 'aus ' . implode(' / ', $this->namen('kategorien', $s['kategorien']));
        if ($s['hersteller']) $kauf[] = 'von ' . implode(' / ', $this->namen('hersteller', $s['hersteller']));
        if ($kauf) $t[] = 'gekauft ' . implode(' ', $kauf) . ' (letzte ' . $s['kauf_monate'] . ' Monate)';
        if ($s['letzter_kauf'] === 'innerhalb')       $t[] = 'gekauft in den letzten ' . $s['letzter_monate'] . ' Monaten';
        if ($s['letzter_kauf'] === 'nicht_innerhalb') $t[] = 'länger als ' . $s['letzter_monate'] . ' Monate nichts gekauft';
        if ($s['angemeldet_seit'] !== '') $t[] = 'angemeldet seit ' . date('d.m.Y', strtotime($s['angemeldet_seit']));
        return implode(' · ', $t);
    }

    /** Auswahllisten für den Editor */
    public function auswahl(): array
    {
        $kat = $this->db->query("SELECT id, parent_id, name FROM kategorien WHERE ist_aktions_kategorie = 0 OR ist_aktions_kategorie IS NULL ORDER BY sortierung, name")->fetchAll(PDO::FETCH_ASSOC);
        $kinder = [];
        foreach ($kat as $k) $kinder[(int)$k['parent_id']][] = $k;
        $baum = [];
        $gehe = function (int $parent, string $pfad) use (&$gehe, &$baum, $kinder) {
            foreach ($kinder[$parent] ?? [] as $k) {
                $name = $pfad === '' ? $k['name'] : $pfad . ' › ' . $k['name'];
                $baum[] = ['id' => (int)$k['id'], 'name' => $name];
                $gehe((int)$k['id'], $name);
            }
        };
        $gehe(0, '');
        return [
            'kundengruppen' => $this->db->query("SELECT id, name FROM kundengruppen WHERE aktiv = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
            'kategorien'    => $baum,
            'hersteller'    => $this->db->query("SELECT id, name FROM hersteller WHERE aktiv = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    private function mitUnterkategorien(array $ids): array
    {
        $alle = array_fill_keys($ids, true);
        $neu = $ids;
        while ($neu) {
            $st = $this->db->query("SELECT id FROM kategorien WHERE parent_id IN (" . implode(',', array_map('intval', $neu)) . ")");
            $neu = array_values(array_filter($st->fetchAll(PDO::FETCH_COLUMN), fn($i) => !isset($alle[$i])));
            foreach ($neu as $i) $alle[$i] = true;
        }
        return array_map('intval', array_keys($alle));
    }

    private function namen(string $tabelle, array $ids): array
    {
        $st = $this->db->query("SELECT name FROM $tabelle WHERE id IN (" . implode(',', array_map('intval', $ids)) . ") ORDER BY name");
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }
}
