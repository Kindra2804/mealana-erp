<?php

/**
 * Datentransfer ERP → ERP: Export
 *
 * Packt ausgewählte Stammdaten eines ERP in ein Transferpaket (.tar), das ein anderes
 * ERP mit DatentransferImport wieder einspielt — z. B. Artikel eines Shops aus der Dev
 * ins Live-ERP, oder später Daten zu einem Kunden.
 *
 * Grundidee: IDs gelten nur in der Quell-Datenbank. Darum enthält das Paket
 *   - die Datenzeilen mit ihren Quell-IDs (zum Verknüpfen untereinander) und
 *   - für alle Stammdaten, auf die verwiesen wird (Steuerklasse, Artikeltyp, Einheit,
 *     Kundengruppe, Shop …), einen „natürlichen Schlüssel“ (Steuersatz, Code, Name …).
 * Der Import ordnet darüber jede Quell-ID einer Ziel-ID zu.
 *
 * Teile (frei kombinierbar):
 *   kategorien  ganzer Kategoriebaum (oder Ast mit kategorie_baum)
 *   hersteller  alle Hersteller (sonst nur die, auf die Artikel verweisen)
 *   artikel     Artikel + Codes, Preise, Bilder, Kategorien, Achsen/Werte, Merkmale,
 *               Lieferanten-Zuordnung, Shop-Zuweisung (ohne Bestand, ohne Shop-IDs)
 *   slider      Slides + Kacheln eines Shops
 *   menue       Hauptmenü eines Shops
 *
 * Bewusst NICHT im Paket: Lagerbestand, Bewegungen, Aufträge, Kunden, Shop-Zugangsdaten,
 * WooCommerce-IDs (das Ziel-ERP legt alles im Shop neu an).
 */
class DatentransferExport
{
    public const FORMAT = 1;
    public const TEILE  = ['kategorien', 'hersteller', 'artikel', 'slider', 'menue'];

    private PDO $db;
    private string $public;
    /** @var array<int,array{0:string,1:string}> [Quelle absolut, Ziel relativ im Paket] */
    private array $dateien = [];
    private array $hinweise = [];

    /** @param string|null $publicPfad public-Ordner (Bilder) — nur für Tests abweichend */
    public function __construct(?PDO $db = null, ?string $publicPfad = null)
    {
        $this->db     = $db ?? Database::getInstance();
        $this->public = rtrim($publicPfad ?? realpath(__DIR__ . '/../../../public'), '/\\');
    }

    /**
     * @param array $opt teile (string[]), shop (slug|kürzel|id), kategorie_baum (id, nur Ast),
     *                   artikel_kategorie (id, Artikel nur aus diesem Ast), alle_artikel (bool), datei (Zielpfad .tar)
     * @return array{erfolg:bool, fehler?:string[], datei?:string, zahlen?:array, hinweise?:string[]}
     */
    public function exportiere(array $opt): array
    {
        $teile = array_values(array_intersect(self::TEILE, $opt['teile'] ?? []));
        if (!$teile) return ['erfolg' => false, 'fehler' => ['Keine gültigen Teile gewählt (' . implode(', ', self::TEILE) . ').']];

        $shop = null;
        if (!empty($opt['shop'])) {
            $shop = $this->findeShop((string)$opt['shop']);
            if (!$shop) return ['erfolg' => false, 'fehler' => ["Shop „{$opt['shop']}“ nicht gefunden."]];
        }
        if ((in_array('slider', $teile, true) || in_array('menue', $teile, true)) && !$shop) {
            return ['erfolg' => false, 'fehler' => ['Für slider/menue bitte einen Shop angeben.']];
        }
        if (in_array('artikel', $teile, true) && !$shop && empty($opt['artikel_kategorie']) && empty($opt['alle_artikel'])) {
            return ['erfolg' => false, 'fehler' => ['Für artikel bitte einen Shop, eine Artikel-Kategorie oder ausdrücklich „alle Artikel“ angeben.']];
        }

        $d = [
            'format'      => self::FORMAT,
            'erstellt_am' => date('c'),
            'quelle'      => [
                'firma'   => $this->einstellung('firmenname'),
                'version' => trim((string)@file_get_contents(__DIR__ . '/../../../VERSION')),
            ],
            'teile'       => $teile,
            'shop_id'     => $shop ? (int)$shop['id'] : null,
        ];

        // ── Artikel ──────────────────────────────────────────────────────
        $artikelIds = [];
        if (in_array('artikel', $teile, true)) {
            $artikelIds = $this->waehleArtikel($shop, $opt);
            $this->exportiereArtikel($d, $artikelIds, $shop);
        }

        // ── Slider / Menü ────────────────────────────────────────────────
        if (in_array('slider', $teile, true)) {
            $d['slides'] = $this->alle('SELECT * FROM shop_slides WHERE shop_id = ? ORDER BY id', [$shop['id']]);
            foreach ($d['slides'] as $s) {
                foreach (['bild_desktop', 'bild_mobil'] as $f) {
                    if ($s[$f]) $this->merkeDatei("uploads/slider/{$s['id']}/{$s[$f]}", "slider/{$s['id']}/{$s[$f]}");
                }
                if ($s['aktion_id']) $this->hinweise[] = "Slide „{$s['bezeichnung']}“ hängt an einer Aktion — die Verknüpfung wird nicht übertragen.";
            }
        }
        if (in_array('menue', $teile, true)) {
            $d['menue'] = $this->alle('SELECT * FROM shop_menue_punkte WHERE shop_id = ? ORDER BY parent_id IS NOT NULL, id', [$shop['id']]);
        }

        // ── Hersteller ───────────────────────────────────────────────────
        $herstellerIds = array_filter(array_column($d['artikel'] ?? [], 'hersteller_id'));
        foreach (array_merge($d['slides'] ?? [], $d['menue'] ?? []) as $z) {
            foreach (['button1', 'button2'] as $b) {
                if (($z["{$b}_ziel_typ"] ?? null) === 'hersteller') $herstellerIds[] = $z["{$b}_ziel_id"];
            }
            if (($z['typ'] ?? null) === 'hersteller') $herstellerIds[] = $z['ziel_id'];
        }
        $d['hersteller'] = in_array('hersteller', $teile, true)
            ? $this->alle('SELECT * FROM hersteller ORDER BY id')
            : $this->zeilenMitIds('hersteller', $herstellerIds);
        foreach ($d['hersteller'] as $h) {
            // logo_pfad = Dateiname unter public/img/hersteller/ (HerstellerService::speichereLogo)
            if ($h['logo_pfad']) $this->merkeDatei('img/hersteller/' . basename($h['logo_pfad']), "hersteller/{$h['id']}/" . basename($h['logo_pfad']));
        }

        // ── Kategorien (ganzer Baum / Ast / nur benötigte + Vorfahren) ───
        $katIds = array_column($d['artikel_kategorien'] ?? [], 'kategorie_id');
        foreach ($d['artikel_kategorie_shop_ausschluss'] ?? [] as $z) $katIds[] = $z['kategorie_id'];
        foreach ($d['slides'] ?? [] as $s) {
            $katIds[] = $s['kategorie_id'];
            foreach (['button1', 'button2'] as $b) if ($s["{$b}_ziel_typ"] === 'kategorie') $katIds[] = $s["{$b}_ziel_id"];
        }
        foreach ($d['menue'] ?? [] as $m) if ($m['typ'] === 'kategorie') $katIds[] = $m['ziel_id'];
        if ($shop && $shop['wurzel_kategorie_id']) $katIds[] = $shop['wurzel_kategorie_id'];

        $alleKat = $this->alle('SELECT * FROM kategorien ORDER BY id');
        $nachId  = array_column($alleKat, null, 'id');
        if (in_array('kategorien', $teile, true)) {
            $katIds = array_merge($katIds, !empty($opt['kategorie_baum'])
                ? $this->astIds($alleKat, (int)$opt['kategorie_baum'])
                : array_keys($nachId));
        }
        $mitVorfahren = [];
        foreach (array_filter($katIds) as $id) {
            for ($k = $nachId[(int)$id] ?? null; $k && !isset($mitVorfahren[$k['id']]); $k = $nachId[$k['parent_id']] ?? null) {
                $mitVorfahren[$k['id']] = true;
            }
        }
        $d['kategorien'] = array_values(array_intersect_key($nachId, $mitVorfahren));
        $ausgeschlossen = $this->alle('SELECT kategorie_id, shop_id FROM kategorie_shops WHERE ausgeschlossen = 1');
        $d['kategorie_ausschluss'] = array_values(array_filter($ausgeschlossen, fn($z) => isset($mitVorfahren[$z['kategorie_id']])));
        foreach ($d['kategorien'] as $k) {
            if ($k['bild_pfad']) $this->merkeDatei("uploads/kategorien/{$k['id']}/{$k['bild_pfad']}", "kategorien/{$k['id']}/{$k['bild_pfad']}");
        }

        // ── Stammdaten-Schlüssel ─────────────────────────────────────────
        $d['ref'] = $this->referenzen($d);

        return $this->schreibePaket($d, $opt['datei'] ?? null);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function waehleArtikel(?array $shop, array $opt): array
    {
        if (!empty($opt['alle_artikel'])) {
            $ids = $this->spalte('SELECT id FROM artikel');
        } else {
            $ids = null;
            if ($shop) $ids = $this->spalte('SELECT artikel_id FROM artikel_shops WHERE shop_id = ?', [$shop['id']]);
            if (!empty($opt['artikel_kategorie'])) {
                $ast = $this->astIds($this->alle('SELECT id, parent_id FROM kategorien'), (int)$opt['artikel_kategorie']);
                $inKat = $ast ? $this->spalte('SELECT DISTINCT artikel_id FROM artikel_kategorien WHERE kategorie_id IN (' . $this->platzhalter($ast) . ')', $ast) : [];
                $ids = $ids === null ? $inKat : array_values(array_intersect($ids, $inKat));
            }
        }
        $ids = array_map('intval', $ids ?? []);
        if (!$ids) return [];
        // Familie vervollständigen: Kinder der Väter und Väter der Kinder
        $ph   = $this->platzhalter($ids);
        $ids  = array_merge($ids,
            $this->spalte("SELECT id FROM artikel WHERE vaterartikel_id IN ($ph)", $ids),
            $this->spalte("SELECT vaterartikel_id FROM artikel WHERE id IN ($ph) AND vaterartikel_id IS NOT NULL", $ids));
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $partner = $this->spalte('SELECT id FROM artikel WHERE partner_id IS NOT NULL AND id IN (' . $this->platzhalter($ids) . ')', $ids);
        if ($partner) $this->hinweise[] = count($partner) . ' Partner-Artikel (Mietfach/Kommission) ausgelassen.';
        return array_values(array_diff($ids, array_map('intval', $partner)));
    }

    private function exportiereArtikel(array &$d, array $ids, ?array $shop): void
    {
        $d['artikel'] = $this->zeilenMitIds('artikel', $ids);
        // Väter vor Kindern, damit der Import in einem Durchgang verknüpfen kann
        usort($d['artikel'], fn($a, $b) => [$a['vaterartikel_id'] !== null, $a['id']] <=> [$b['vaterartikel_id'] !== null, $b['id']]);

        $tabellen = ['artikel_codes', 'artikel_kategorien', 'artikel_preise', 'artikel_staffelpreise', 'artikel_bilder',
                     'artikel_achsen', 'artikel_merkmale', 'artikel_lieferanten', 'artikel_kategorie_shop_ausschluss'];
        foreach ($tabellen as $t) $d[$t] = $ids ? $this->zeilenMitIds($t, $ids, 'artikel_id') : [];

        // Shop-Zuweisung ohne WooCommerce-IDs/Sync-Stand
        $d['artikel_shops'] = $ids ? $this->alle('SELECT artikel_id, shop_id, aktiv FROM artikel_shops WHERE artikel_id IN (' . $this->platzhalter($ids) . ')', $ids) : [];
        if ($shop) $d['artikel_shops'] = array_values(array_filter($d['artikel_shops'], fn($z) => (int)$z['shop_id'] === (int)$shop['id']));

        $d['achse_werte']       = $ids ? $this->zeilenMitIds('varianten_achse_werte', $ids, 'artikel_id') : [];
        $d['kombination_werte'] = $ids ? $this->zeilenMitIds('varianten_kombination_werte', $ids, 'kombination_id') : [];
        $achsIds = array_merge(array_column($d['artikel_achsen'], 'achse_id'), array_column($d['achse_werte'], 'achse_id'));
        $d['achsen'] = $this->zeilenMitIds('varianten_achsen', $achsIds);
        // abhängige Achsen mitnehmen
        $abh = array_filter(array_column($d['achsen'], 'abhaengig_von_achse_id'));
        if ($abh) $d['achsen'] = array_values(array_column(array_merge($d['achsen'], $this->zeilenMitIds('varianten_achsen', $abh)), null, 'id'));

        foreach ($d['artikel_bilder'] as $b) {
            $this->merkeDatei("uploads/artikel/{$b['artikel_id']}/{$b['dateiname']}", "artikel/{$b['artikel_id']}/{$b['dateiname']}");
        }
        $downloads = array_filter($d['artikel'], fn($a) => !empty($a['download_dateiname']));
        if ($downloads) $this->hinweise[] = count($downloads) . ' Download-Artikel: die Download-Dateien selbst werden nicht übertragen.';
    }

    /** Natürliche Schlüssel aller Stammdaten, auf die das Paket verweist. */
    private function referenzen(array $d): array
    {
        $a   = $d['artikel'] ?? [];
        $ref = [
            'steuerklassen'   => $this->zeilenMitIds('steuerklassen', array_column($a, 'steuerklasse_id')),
            'artikel_typen'   => $this->zeilenMitIds('artikel_typen', array_column($a, 'artikeltyp_id')),
            'einheiten'       => $this->zeilenMitIds('einheiten', array_column($a, 'einheit_id')),
            'versandklassen'  => $this->zeilenMitIds('versandklassen', array_column($a, 'versandklasse_id')),
            'kundengruppen'   => $this->zeilenMitIds('kundengruppen', array_merge(
                                    array_column($d['artikel_preise'] ?? [], 'kundengruppen_id'),
                                    array_column($d['artikel_staffelpreise'] ?? [], 'kundengruppen_id'))),
            'lieferanten'     => [],
            'merkmale'        => $this->zeilenMitIds('merkmale', array_column($d['artikel_merkmale'] ?? [], 'merkmal_id')),
            'merkmal_werte'   => $this->zeilenMitIds('merkmal_werte', array_column($d['artikel_merkmale'] ?? [], 'merkmal_wert_id')),
        ];
        $ref['artikel_gruppen'] = $this->zeilenMitIds('artikel_gruppen', array_merge(
            array_column($a, 'artikel_gruppe_id'), array_column($ref['versandklassen'], 'artikel_gruppe_id')));
        $ref['merkmal_gruppen'] = $this->zeilenMitIds('merkmal_gruppen', array_column($ref['merkmale'], 'merkmal_gruppen_id'));
        foreach ($this->zeilenMitIds('lieferanten', array_column($d['artikel_lieferanten'] ?? [], 'lieferant_id')) as $l) {
            $ref['lieferanten'][] = ['id' => $l['id'], 'name' => $l['name'], 'firma' => $l['firma']];
        }
        $shopIds = array_merge([$d['shop_id']], array_column($d['artikel_shops'] ?? [], 'shop_id'),
            array_column($d['artikel_kategorie_shop_ausschluss'] ?? [], 'shop_id'), array_column($d['kategorie_ausschluss'] ?? [], 'shop_id'));
        $ref['shops'] = [];
        foreach ($this->zeilenMitIds('shops', $shopIds) as $s) {
            $ref['shops'][] = array_intersect_key($s, array_flip(['id', 'slug', 'nr_kuerzel', 'name', 'logo_pfad', 'sub_marke',
                'wurzel_kategorie_id', 'slider_intervall_sek', 'menue_orte']));
            if ($s['logo_pfad'] && (int)$s['id'] === (int)$d['shop_id']) $this->merkeDatei($s['logo_pfad'], "shops/{$s['id']}/" . basename($s['logo_pfad']));
        }
        return $ref;
    }

    private function schreibePaket(array $d, ?string $datei): array
    {
        $datei ??= __DIR__ . '/../../../../exports/datentransfer_' . date('Y-m-d_His') . '.tar';
        if (!str_ends_with($datei, '.tar')) $datei .= '.tar';
        @mkdir(dirname($datei), 0777, true);
        if (file_exists($datei)) unlink($datei);

        // Erst in einen Arbeitsordner, dann in einem Rutsch packen (addFile je Datei ist sehr langsam)
        $arbeit = sys_get_temp_dir() . '/nahtlos_export_' . getmypid() . '_' . time();
        mkdir("$arbeit/dateien", 0777, true);
        file_put_contents("$arbeit/daten.json", json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE));
        $fehlend = [];
        foreach ($this->dateien as [$quelle, $ziel]) {
            if (!is_file($quelle)) { $fehlend[] = $ziel; continue; }
            @mkdir(dirname("$arbeit/dateien/$ziel"), 0777, true);
            copy($quelle, "$arbeit/dateien/$ziel");
        }
        (new PharData($datei))->buildFromDirectory($arbeit);
        $this->entferne($arbeit);
        if ($fehlend) {
            $this->hinweise[] = count($fehlend) . ' Bilddatei(en) fehlen in der Quelle und sind nicht im Paket: '
                . implode(', ', array_slice($fehlend, 0, 5)) . (count($fehlend) > 5 ? ' …' : '');
        }
        $fehlend = count($fehlend);

        $zahlen = [];
        foreach (['kategorien', 'hersteller', 'achsen', 'artikel', 'artikel_bilder', 'artikel_preise', 'achse_werte', 'slides', 'menue'] as $k) {
            if (isset($d[$k])) $zahlen[$k] = count($d[$k]);
        }
        $zahlen['dateien'] = count($this->dateien) - $fehlend;
        return ['erfolg' => true, 'datei' => realpath($datei) ?: $datei, 'zahlen' => $zahlen, 'hinweise' => $this->hinweise];
    }

    // ── kleine Helfer ───────────────────────────────────────────────────

    private function entferne(string $pfad): void
    {
        if (is_dir($pfad)) {
            foreach (array_diff(scandir($pfad), ['.', '..']) as $e) $this->entferne("$pfad/$e");
            @rmdir($pfad);
        } elseif (file_exists($pfad)) {
            @unlink($pfad);
        }
    }

    private function findeShop(string $s): array|false
    {
        $st = $this->db->prepare('SELECT * FROM shops WHERE slug = :s OR nr_kuerzel = :s OR id = :i LIMIT 1');
        $st->execute(['s' => $s, 'i' => ctype_digit($s) ? (int)$s : -1]);
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    private function astIds(array $kategorien, int $wurzel): array
    {
        $kinder = [];
        foreach ($kategorien as $k) $kinder[(int)$k['parent_id']][] = (int)$k['id'];
        $ids = [];
        for ($offen = [$wurzel]; $offen; ) {
            $id = array_pop($offen);
            $ids[] = $id;
            array_push($offen, ...($kinder[$id] ?? []));
        }
        return $ids;
    }

    private function merkeDatei(string $relativZuPublic, string $zielImPaket): void
    {
        $this->dateien[$zielImPaket] = [$this->public . '/' . ltrim($relativZuPublic, '/'), $zielImPaket];
    }

    private function zeilenMitIds(string $tabelle, array $ids, string $spalte = 'id'): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        return $this->alle("SELECT * FROM $tabelle WHERE $spalte IN (" . $this->platzhalter($ids) . ')', $ids);
    }

    private function alle(string $sql, array $p = []): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function spalte(string $sql, array $p = []): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    private function platzhalter(array $werte): string
    {
        return implode(',', array_fill(0, count($werte), '?'));
    }

    private function einstellung(string $schluessel): ?string
    {
        $st = $this->db->prepare('SELECT wert FROM system_einstellungen WHERE schluessel = ?');
        $st->execute([$schluessel]);
        return $st->fetchColumn() ?: null;
    }
}
