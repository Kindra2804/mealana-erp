<?php

/**
 * Datentransfer ERP → ERP: Import (Gegenstück zu DatentransferExport)
 *
 * Spielt ein Transferpaket in dieses ERP ein. Jede Quell-ID wird über einen natürlichen
 * Schlüssel einer Ziel-ID zugeordnet („Map“), bevor etwas verknüpft wird:
 *
 *   Steuerklasse  → Satz + Land          Artikeltyp   → Code
 *   Einheit       → Kürzel, sonst Name    Artikelgruppe → Konto-Nr. (fehlt → anlegen)
 *   Versandklasse → Code (fehlt → anlegen) Kundengruppe → Typ + Name, sonst Standard
 *   Lieferant     → Name (fehlt → Zuordnung weglassen)
 *   Shop          → Slug, sonst Kürzel, sonst Name (fehlt → Shop-Teile weglassen)
 *   Kategorie     → gleicher Name unter gleicher Elternkategorie (fehlt → anlegen)
 *   Hersteller    → Name (fehlt → anlegen)   Achse → Code, sonst Name (fehlt → anlegen)
 *   Artikel       → Artikelnummer: gibt es sie schon, bleibt der Artikel UNVERÄNDERT
 *                   (nur seine ID wird für Verknüpfungen benutzt)
 *
 * Alles läuft in EINER Transaktion; die Bilder werden erst nach erfolgreichem Datenteil
 * kopiert. Im Probelauf wird am Ende zurückgerollt und keine Datei kopiert.
 * Spalten werden mit der Ziel-Tabelle abgeglichen — ein Paket aus einer älteren oder
 * neueren Version funktioniert, solange die Kernspalten passen.
 */
class DatentransferImport
{
    private PDO $db;
    private string $public;
    private array $spaltenCache = [];
    /** @var array<string,array<int,int>> Quell-ID → Ziel-ID je Art */
    private array $map = [];
    /** Artikel, die es im Ziel schon gab (Quell-ID → true) — an ihnen wird nichts geändert */
    private array $vorhanden = [];
    private array $zahlen = [];
    private array $hinweise = [];
    /** @var array<int,array{0:string,1:string}> [Quelle, Ziel absolut] */
    private array $kopieren = [];

    /** @param string|null $publicPfad public-Ordner (Bilder) — nur für Tests abweichend */
    public function __construct(?PDO $db = null, ?string $publicPfad = null)
    {
        $this->db     = $db ?? Database::getInstance();
        $this->public = rtrim($publicPfad ?? realpath(__DIR__ . '/../../../public'), '/\\');
    }

    /**
     * @return array{erfolg:bool, fehler?:string[], zahlen?:array, hinweise?:string[], probelauf?:bool}
     */
    public function importiere(string $paket, bool $probelauf = false): array
    {
        if (!is_file($paket)) return ['erfolg' => false, 'fehler' => ["Paket nicht gefunden: $paket"]];
        $tmp = sys_get_temp_dir() . '/nahtlos_transfer_' . getmypid() . '_' . time();
        try {
            (new PharData($paket))->extractTo($tmp, null, true);
        } catch (Throwable $e) {
            return ['erfolg' => false, 'fehler' => ['Paket lässt sich nicht öffnen: ' . $e->getMessage()]];
        }
        $d = json_decode((string)@file_get_contents("$tmp/daten.json"), true);
        if (!is_array($d) || ($d['format'] ?? null) !== DatentransferExport::FORMAT) {
            $this->entferne($tmp);
            return ['erfolg' => false, 'fehler' => ['daten.json fehlt oder hat ein unbekanntes Format.']];
        }

        $this->db->beginTransaction();
        try {
            $this->stammdaten($d['ref'] ?? []);
            $this->kategorien($d, $tmp);
            $this->hersteller($d['hersteller'] ?? [], $tmp);
            $this->achsen($d['achsen'] ?? []);
            if (isset($d['artikel'])) $this->artikel($d, $tmp);
            if (isset($d['slides'])) $this->slides($d, $tmp);
            if (isset($d['menue'])) $this->menue($d);
            $this->shopEinstellungen($d, $tmp);

            if ($probelauf) {
                $this->db->rollBack();
            } else {
                $this->kopiereDateien();   // wirft bei Fehler → Rollback unten
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if (!$probelauf) $this->raeumeKopienAuf();
            $this->entferne($tmp);
            return ['erfolg' => false, 'fehler' => [$e->getMessage()], 'hinweise' => $this->hinweise];
        }
        $this->entferne($tmp);
        return ['erfolg' => true, 'probelauf' => $probelauf, 'zahlen' => $this->zahlen, 'hinweise' => array_values(array_unique($this->hinweise))];
    }

    // ── Stammdaten zuordnen ─────────────────────────────────────────────

    private function stammdaten(array $ref): void
    {
        foreach ($ref['steuerklassen'] ?? [] as $z) {
            $id = $this->wert('SELECT id FROM steuerklassen WHERE satz = ? AND land = ? ORDER BY aktiv DESC LIMIT 1', [$z['satz'], $z['land']])
               ?: $this->wert('SELECT id FROM steuerklassen WHERE satz = ? ORDER BY aktiv DESC LIMIT 1', [$z['satz']]);
            if (!$id) throw new RuntimeException("Steuerklasse {$z['satz']} % ({$z['land']}) fehlt im Ziel — bitte zuerst anlegen.");
            $this->map['steuerklassen'][$z['id']] = (int)$id;
        }
        foreach ($ref['artikel_typen'] ?? [] as $z) {
            $id = $this->wert('SELECT id FROM artikel_typen WHERE code = ?', [$z['code']]);
            if (!$id) throw new RuntimeException("Artikeltyp „{$z['code']}“ fehlt im Ziel — bitte zuerst anlegen.");
            $this->map['artikel_typen'][$z['id']] = (int)$id;
        }
        foreach ($ref['einheiten'] ?? [] as $z) {
            $id = $this->wert('SELECT id FROM einheiten WHERE kuerzel = ? LIMIT 1', [$z['kuerzel']])
               ?: $this->wert('SELECT id FROM einheiten WHERE name = ? LIMIT 1', [$z['name']])
               ?: $this->neu('einheiten', $z, 'einheiten');
            $this->map['einheiten'][$z['id']] = (int)$id;
        }
        foreach ($ref['artikel_gruppen'] ?? [] as $z) {
            $id = $this->wert('SELECT id FROM artikel_gruppen WHERE konto_nr = ? ORDER BY aktiv DESC LIMIT 1', [$z['konto_nr']])
               ?: $this->neu('artikel_gruppen', $z, 'artikel_gruppen');
            $this->map['artikel_gruppen'][$z['id']] = (int)$id;
        }
        foreach ($ref['versandklassen'] ?? [] as $z) {
            $id = $this->wert('SELECT id FROM versandklassen WHERE code = ? LIMIT 1', [$z['code']]);
            if (!$id) {
                $z['artikel_gruppe_id'] = $this->ziel('artikel_gruppen', $z['artikel_gruppe_id']);
                $id = $this->neu('versandklassen', $z, 'versandklassen');
            }
            $this->map['versandklassen'][$z['id']] = (int)$id;
        }
        foreach ($ref['kundengruppen'] ?? [] as $z) {
            $id = $this->wert('SELECT id FROM kundengruppen WHERE typ = ? AND name = ? LIMIT 1', [$z['typ'], $z['name']])
               ?: ($z['ist_standard'] ? $this->wert('SELECT id FROM kundengruppen WHERE ist_standard = 1 LIMIT 1') : null);
            if ($id) $this->map['kundengruppen'][$z['id']] = (int)$id;
            else $this->hinweise[] = "Kundengruppe „{$z['name']}“ fehlt im Ziel — ihre Preise werden ausgelassen.";
        }
        foreach ($ref['lieferanten'] ?? [] as $z) {
            $id = $this->wert('SELECT id FROM lieferanten WHERE name = ? LIMIT 1', [$z['name']]);
            if ($id) $this->map['lieferanten'][$z['id']] = (int)$id;
            else $this->hinweise[] = "Lieferant „{$z['name']}“ fehlt im Ziel — Lieferanten-Zuordnung ausgelassen.";
        }
        foreach ($ref['merkmal_gruppen'] ?? [] as $z) {
            $this->map['merkmal_gruppen'][$z['id']] = (int)($this->wert('SELECT id FROM merkmal_gruppen WHERE name = ? LIMIT 1', [$z['name']])
                ?: $this->neu('merkmal_gruppen', $z, 'merkmal_gruppen'));
        }
        foreach ($ref['merkmale'] ?? [] as $z) {
            $z['merkmal_gruppen_id'] = $this->ziel('merkmal_gruppen', $z['merkmal_gruppen_id']);
            $this->map['merkmale'][$z['id']] = (int)($this->wert('SELECT id FROM merkmale WHERE name = ? LIMIT 1', [$z['name']])
                ?: $this->neu('merkmale', $z, 'merkmale'));
        }
        foreach ($ref['merkmal_werte'] ?? [] as $z) {
            $z['merkmal_id'] = $this->ziel('merkmale', $z['merkmal_id']);
            $this->map['merkmal_werte'][$z['id']] = (int)($this->wert('SELECT id FROM merkmal_werte WHERE merkmal_id = ? AND wert = ? LIMIT 1', [$z['merkmal_id'], $z['wert']])
                ?: $this->neu('merkmal_werte', $z, 'merkmal_werte'));
        }
        foreach ($ref['shops'] ?? [] as $z) {
            $id = $this->wert('SELECT id FROM shops WHERE slug = ? LIMIT 1', [$z['slug']])
               ?: ($z['nr_kuerzel'] !== '' && $z['nr_kuerzel'] !== null ? $this->wert('SELECT id FROM shops WHERE nr_kuerzel = ? LIMIT 1', [$z['nr_kuerzel']]) : null)
               ?: $this->wert('SELECT id FROM shops WHERE name = ? LIMIT 1', [$z['name']]);
            if ($id) $this->map['shops'][$z['id']] = (int)$id;
            else $this->fehlendeShops[$z['id']] = "„{$z['name']}“";
        }
    }

    /** Quell-Shop-IDs ohne Gegenstück im Ziel → Name (Hinweis erst, wenn wirklich etwas daran hängt) */
    private array $fehlendeShops = [];

    private function shopFehlt($quellId, string $was): void
    {
        if (isset($this->fehlendeShops[$quellId])) {
            $this->hinweise[] = "Kanal {$this->fehlendeShops[$quellId]} gibt es im Ziel nicht — $was ausgelassen (Kanal anlegen und Import wiederholen, Vorhandenes bleibt unberührt).";
        }
    }

    // ── Kategorien ──────────────────────────────────────────────────────

    private function kategorien(array $d, string $tmp): void
    {
        $nachId = array_column($d['kategorien'] ?? [], null, 'id');
        $tiefe = function ($k) use ($nachId) {
            for ($t = 0; $k['parent_id'] && isset($nachId[$k['parent_id']]); $t++) $k = $nachId[$k['parent_id']];
            return $t;
        };
        $liste = array_values($nachId);
        usort($liste, fn($a, $b) => [$tiefe($a), $a['sortierung'], $a['id']] <=> [$tiefe($b), $b['sortierung'], $b['id']]);

        foreach ($liste as $k) {
            $eltern = $k['parent_id'] ? $this->ziel('kategorien', $k['parent_id']) : null;
            $id = $eltern
                ? $this->wert('SELECT id FROM kategorien WHERE parent_id = ? AND name = ? LIMIT 1', [$eltern, $k['name']])
                : $this->wert('SELECT id FROM kategorien WHERE parent_id IS NULL AND name = ? LIMIT 1', [$k['name']]);
            if ($id) {
                $this->zaehle('kategorien_vorhanden');
            } else {
                $k['parent_id'] = $eltern;
                $id = $this->neu('kategorien', $k, 'kategorien');
                if ($k['bild_pfad']) $this->merkeKopie($tmp, "kategorien/{$k['id']}/{$k['bild_pfad']}", "uploads/kategorien/$id/{$k['bild_pfad']}");
            }
            $this->map['kategorien'][$k['id']] = (int)$id;
        }
        foreach ($d['kategorie_ausschluss'] ?? [] as $z) {
            $kat = $this->map['kategorien'][$z['kategorie_id']] ?? null;
            $shop = $this->map['shops'][$z['shop_id']] ?? null;
            if (!$shop) $this->shopFehlt($z['shop_id'], 'ausgeblendete Kategorien dieses Kanals');
            if (!$kat || !$shop) continue;
            $this->ausfuehren('INSERT INTO kategorie_shops (kategorie_id, shop_id, ausgeschlossen) VALUES (?, ?, 1)
                               ON DUPLICATE KEY UPDATE ausgeschlossen = 1', [$kat, $shop]);
        }
    }

    // ── Hersteller, Achsen ──────────────────────────────────────────────

    private function hersteller(array $zeilen, string $tmp): void
    {
        foreach ($zeilen as $h) {
            $id = $this->wert('SELECT id FROM hersteller WHERE name = ? LIMIT 1', [$h['name']]);
            if ($id) {
                $this->zaehle('hersteller_vorhanden');
            } else {
                $logo = $h['logo_pfad'];
                $h['logo_pfad'] = null;
                $id = $this->neu('hersteller', $h, 'hersteller');
                if ($logo) {
                    // wie HerstellerService::speichereLogo: Dateiname = Hersteller-ID, Ordner img/hersteller/
                    $neuName = "$id." . pathinfo($logo, PATHINFO_EXTENSION);
                    if (is_file("$tmp/dateien/hersteller/{$h['id']}/" . basename($logo))) {
                        $this->merkeKopie($tmp, "hersteller/{$h['id']}/" . basename($logo), "img/hersteller/$neuName");
                        $this->ausfuehren('UPDATE hersteller SET logo_pfad = ? WHERE id = ?', [$neuName, $id]);
                    }
                }
            }
            $this->map['hersteller'][$h['id']] = (int)$id;
        }
    }

    private function achsen(array $zeilen): void
    {
        // Achsen ohne Abhängigkeit zuerst
        usort($zeilen, fn($a, $b) => [$a['abhaengig_von_achse_id'] !== null, $a['id']] <=> [$b['abhaengig_von_achse_id'] !== null, $b['id']]);
        foreach ($zeilen as $a) {
            $id = ($a['code'] ? $this->wert('SELECT id FROM varianten_achsen WHERE code = ? LIMIT 1', [$a['code']]) : null)
               ?: $this->wert('SELECT id FROM varianten_achsen WHERE name = ? LIMIT 1', [$a['name']]);
            if ($id) {
                $this->zaehle('achsen_vorhanden');
            } else {
                $a['abhaengig_von_achse_id'] = $a['abhaengig_von_achse_id'] ? ($this->map['varianten_achsen'][$a['abhaengig_von_achse_id']] ?? null) : null;
                $id = $this->neu('varianten_achsen', $a, 'achsen');
            }
            $this->map['varianten_achsen'][$a['id']] = (int)$id;
        }
    }

    // ── Artikel ─────────────────────────────────────────────────────────

    private function artikel(array $d, string $tmp): void
    {
        // 1. Artikel-Zeilen (Väter vor Kindern, im Export so sortiert)
        foreach ($d['artikel'] as $a) {
            $id = $this->wert('SELECT id FROM artikel WHERE artikelnummer = ?', [$a['artikelnummer']]);
            if ($id) {
                $this->map['artikel'][$a['id']] = (int)$id;
                $this->vorhanden[$a['id']] = true;
                $this->zaehle('artikel_vorhanden');
                continue;
            }
            $a['hersteller_id']     = $this->ziel('hersteller', $a['hersteller_id']);
            $a['steuerklasse_id']   = $this->ziel('steuerklassen', $a['steuerklasse_id']);
            $a['artikeltyp_id']     = $this->ziel('artikel_typen', $a['artikeltyp_id']);
            $a['einheit_id']        = $this->ziel('einheiten', $a['einheit_id']);
            $a['artikel_gruppe_id'] = $this->ziel('artikel_gruppen', $a['artikel_gruppe_id']);
            $a['versandklasse_id']  = $this->ziel('versandklassen', $a['versandklasse_id']);
            $a['vaterartikel_id']   = $this->ziel('artikel', $a['vaterartikel_id']);
            $zustandVater           = $a['zustand_vater_id'];
            $a['zustand_vater_id']  = null;
            $a['partner_id'] = $a['stammplatz_id'] = $a['nachfuellplatz_id'] = $a['letzte_inventur_am'] = null;
            if ($a['url_slug'] && $this->wert('SELECT id FROM artikel WHERE url_slug = ?', [$a['url_slug']])) {
                $this->hinweise[] = "URL-Slug „{$a['url_slug']}“ ist im Ziel schon vergeben — bei {$a['artikelnummer']} leer gelassen.";
                $a['url_slug'] = null;
            }
            $neuId = $this->neu('artikel', $a, 'artikel');
            $this->map['artikel'][$a['id']] = $neuId;
            if ($zustandVater) $this->spaeter[] = [$neuId, $zustandVater];
        }
        foreach ($this->spaeter ?? [] as [$neuId, $quellVater]) {
            $this->ausfuehren('UPDATE artikel SET zustand_vater_id = ? WHERE id = ?', [$this->map['artikel'][$quellVater] ?? null, $neuId]);
        }

        $neu = fn(array $z, string $spalte = 'artikel_id') => !isset($this->vorhanden[$z[$spalte]]) && isset($this->map['artikel'][$z[$spalte]]);

        // 2. Einfache 1:n-Tabellen
        foreach ($d['artikel_codes'] as $z) if ($neu($z)) {
            $z['artikel_id'] = $this->map['artikel'][$z['artikel_id']];
            $this->neu('artikel_codes', $z, 'codes');
        }
        foreach ($d['artikel_kategorien'] as $z) if ($neu($z) && ($kat = $this->map['kategorien'][$z['kategorie_id']] ?? null)) {
            $this->ausfuehren('INSERT IGNORE INTO artikel_kategorien (artikel_id, kategorie_id) VALUES (?, ?)', [$this->map['artikel'][$z['artikel_id']], $kat]);
            $this->zaehle('artikel_kategorien');
        }
        foreach (['artikel_preise' => 'preise', 'artikel_staffelpreise' => 'staffelpreise'] as $t => $name) {
            foreach ($d[$t] as $z) if ($neu($z) && ($kg = $this->map['kundengruppen'][$z['kundengruppen_id']] ?? null)) {
                $z['artikel_id'] = $this->map['artikel'][$z['artikel_id']];
                $z['kundengruppen_id'] = $kg;
                $this->neu($t, $z, $name);
            }
        }
        foreach ($d['artikel_bilder'] as $z) if ($neu($z)) {
            $quellId = $z['artikel_id'];
            $z['artikel_id'] = $this->map['artikel'][$quellId];
            $this->neu('artikel_bilder', $z, 'bilder');
            $this->merkeKopie($tmp, "artikel/$quellId/{$z['dateiname']}", "uploads/artikel/{$z['artikel_id']}/{$z['dateiname']}");
        }
        foreach ($d['artikel_merkmale'] as $z) if ($neu($z)) {
            $z['artikel_id'] = $this->map['artikel'][$z['artikel_id']];
            $z['merkmal_id'] = $this->ziel('merkmale', $z['merkmal_id']);
            $z['merkmal_wert_id'] = $this->ziel('merkmal_werte', $z['merkmal_wert_id']);
            if ($z['merkmal_id']) $this->neu('artikel_merkmale', $z, 'merkmale');
        }
        foreach ($d['artikel_lieferanten'] as $z) if ($neu($z) && ($l = $this->map['lieferanten'][$z['lieferant_id']] ?? null)) {
            $z['artikel_id'] = $this->map['artikel'][$z['artikel_id']];
            $z['lieferant_id'] = $l;
            $this->neu('artikel_lieferanten', $z, 'lieferanten_zuordnungen');
        }
        foreach ($d['artikel_shops'] as $z) if ($neu($z) && !isset($this->map['shops'][$z['shop_id']])) $this->shopFehlt($z['shop_id'], 'Artikel-Zuweisungen');
        foreach ($d['artikel_shops'] as $z) if ($neu($z) && ($shop = $this->map['shops'][$z['shop_id']] ?? null)) {
            $this->ausfuehren("INSERT IGNORE INTO artikel_shops (artikel_id, shop_id, aktiv, sync_status) VALUES (?, ?, ?, 'pending')",
                [$this->map['artikel'][$z['artikel_id']], $shop, $z['aktiv']]);
            $this->zaehle('shop_zuweisungen');
        }
        foreach ($d['artikel_kategorie_shop_ausschluss'] as $z) {
            $kat = $this->map['kategorien'][$z['kategorie_id']] ?? null;
            $shop = $this->map['shops'][$z['shop_id']] ?? null;
            if ($neu($z) && $kat && $shop) {
                $this->ausfuehren('INSERT IGNORE INTO artikel_kategorie_shop_ausschluss (artikel_id, kategorie_id, shop_id) VALUES (?, ?, ?)',
                    [$this->map['artikel'][$z['artikel_id']], $kat, $shop]);
            }
        }

        // 3. Achsen-Werte (gehören dem Vater bzw. Konfigurator-Artikel)
        $bedingungen = [];
        foreach ($d['achse_werte'] as $w) {
            $artikel = $this->map['artikel'][$w['artikel_id']] ?? null;
            $achse   = $this->map['varianten_achsen'][$w['achse_id']] ?? null;
            if (!$artikel || !$achse) continue;
            if (isset($this->vorhanden[$w['artikel_id']])) {
                // Artikel gab es schon: vorhandenen Wert über den Text finden (für Kinder-Verknüpfungen)
                $id = $this->wert('SELECT id FROM varianten_achse_werte WHERE artikel_id = ? AND achse_id = ? AND wert = ? LIMIT 1', [$artikel, $achse, $w['wert']]);
                if ($id) $this->map['werte'][$w['id']] = (int)$id;
                continue;
            }
            $bedingungen[] = $w;
            $w['artikel_id'] = $artikel;
            $w['achse_id'] = $achse;
            $w['bedingungs_wert_id'] = $w['rohmaterial_artikel_id'] = null;
            $this->map['werte'][$w['id']] = $this->neu('varianten_achse_werte', $w, 'achsen_werte');
        }
        foreach ($bedingungen as $w) {
            $bed = $w['bedingungs_wert_id'] ? ($this->map['werte'][$w['bedingungs_wert_id']] ?? null) : null;
            $roh = $w['rohmaterial_artikel_id'] ? ($this->map['artikel'][$w['rohmaterial_artikel_id']] ?? null) : null;
            if ($bed || $roh) {
                $this->ausfuehren('UPDATE varianten_achse_werte SET bedingungs_wert_id = ?, rohmaterial_artikel_id = ? WHERE id = ?', [$bed, $roh, $this->map['werte'][$w['id']]]);
            }
        }
        foreach ($d['artikel_achsen'] as $z) if ($neu($z)) {
            $z['artikel_id'] = $this->map['artikel'][$z['artikel_id']];
            $z['achse_id'] = $this->map['varianten_achsen'][$z['achse_id']] ?? null;
            $z['bedingungs_achse_id'] = $z['bedingungs_achse_id'] ? ($this->map['varianten_achsen'][$z['bedingungs_achse_id']] ?? null) : null;
            $z['bedingungs_wert_id'] = $z['bedingungs_wert_id'] ? ($this->map['werte'][$z['bedingungs_wert_id']] ?? null) : null;
            if ($z['achse_id']) $this->neu('artikel_achsen', $z, 'artikel_achsen');
        }
        foreach ($d['kombination_werte'] as $z) if ($neu($z, 'kombination_id')) {
            $wert = $this->map['werte'][$z['wert_id']] ?? null;
            if (!$wert) { $this->hinweise[] = 'Ein Varianten-Wert eines Kind-Artikels ließ sich nicht zuordnen.'; continue; }
            $this->ausfuehren('INSERT IGNORE INTO varianten_kombination_werte (kombination_id, wert_id) VALUES (?, ?)', [$this->map['artikel'][$z['kombination_id']], $wert]);
            $this->zaehle('kombination_werte');
        }
    }

    private array $spaeter = [];

    // ── Slider + Menü ───────────────────────────────────────────────────

    private function slides(array $d, string $tmp): void
    {
        $shop = $this->map['shops'][$d['shop_id']] ?? null;
        if (!$shop) $this->shopFehlt($d['shop_id'], 'Slider und Kacheln');
        if (!$shop || !$d['slides']) return;
        if ($this->wert('SELECT COUNT(*) FROM shop_slides WHERE shop_id = ?', [$shop])) {
            $this->hinweise[] = 'Der Ziel-Kanal hat schon Slides/Kacheln — Slider nicht übertragen (sonst doppelt).';
            return;
        }
        foreach ($d['slides'] as $s) {
            $quellId = $s['id'];
            $s['shop_id'] = $shop;
            $s['kategorie_id'] = $this->ziel('kategorien', $s['kategorie_id']);
            foreach (['button1', 'button2'] as $b) $s["{$b}_ziel_id"] = $this->zielNachTyp($s["{$b}_ziel_typ"], $s["{$b}_ziel_id"]);
            $s['aktion_id'] = null;
            foreach (['desktop', 'mobil'] as $v) {
                $s["bild_{$v}_wp_id"] = $s["bild_{$v}_wp_url"] = null;
                $s["bild_{$v}_synced"] = 0;
            }
            $id = $this->neu('shop_slides', $s, 'slides');
            foreach (['bild_desktop', 'bild_mobil'] as $f) {
                if ($s[$f]) $this->merkeKopie($tmp, "slider/$quellId/{$s[$f]}", "uploads/slider/$id/{$s[$f]}");
            }
        }
        $this->ausfuehren('UPDATE shops SET slider_geaendert = 1 WHERE id = ?', [$shop]);
    }

    private function menue(array $d): void
    {
        $shop = $this->map['shops'][$d['shop_id']] ?? null;
        if (!$shop) $this->shopFehlt($d['shop_id'], 'das Hauptmenü');
        if (!$shop || !$d['menue']) return;
        if ($this->wert('SELECT COUNT(*) FROM shop_menue_punkte WHERE shop_id = ?', [$shop])) {
            $this->hinweise[] = 'Der Ziel-Kanal hat schon ein Menü — Menü nicht übertragen (sonst doppelt).';
            return;
        }
        $punkte = [];
        foreach ($d['menue'] as $m) {   // Export: Hauptpunkte zuerst
            $m['shop_id']   = $shop;
            $m['parent_id'] = $m['parent_id'] ? ($punkte[$m['parent_id']] ?? null) : null;
            $m['ziel_id']   = $this->zielNachTyp($m['typ'], $m['ziel_id']);
            $punkte[$m['id']] = $this->neu('shop_menue_punkte', $m, 'menuepunkte');
        }
        // Menü-Orte gehören zum Menü (wo es im Theme erscheint) → mit übernehmen
        $orte = null;
        foreach ($d['ref']['shops'] ?? [] as $q) if ((int)$q['id'] === (int)$d['shop_id']) $orte = $q['menue_orte'];
        $this->ausfuehren('UPDATE shops SET menue_geaendert = 1, menue_orte = COALESCE(?, menue_orte) WHERE id = ?', [$orte ?: null, $shop]);
    }

    /** Leere Kanal-Einstellungen aus der Quelle übernehmen (Wurzelkategorie, Logo, Menü-Orte …) — Vorhandenes bleibt. */
    private function shopEinstellungen(array $d, string $tmp): void
    {
        foreach ($d['ref']['shops'] ?? [] as $q) {
            if ((int)$q['id'] !== (int)($d['shop_id'] ?? 0) || !($ziel = $this->map['shops'][$q['id']] ?? null)) continue;
            $z = $this->zeile('SELECT * FROM shops WHERE id = ?', [$ziel]);
            $wurzel = $q['wurzel_kategorie_id'] ? ($this->map['kategorien'][$q['wurzel_kategorie_id']] ?? null) : null;
            if (!$z['wurzel_kategorie_id'] && $wurzel) {
                $this->ausfuehren('UPDATE shops SET wurzel_kategorie_id = ? WHERE id = ?', [$wurzel, $ziel]);
                $this->hinweise[] = 'Kanal: „Kategorien im Shop ab“ aus der Quelle übernommen.';
            }
            if ($q['slider_intervall_sek']) $this->ausfuehren('UPDATE shops SET slider_intervall_sek = ? WHERE id = ?', [$q['slider_intervall_sek'], $ziel]);
            if (!$z['logo_pfad'] && $q['logo_pfad'] && is_file("$tmp/dateien/shops/{$q['id']}/" . basename($q['logo_pfad']))) {
                $this->merkeKopie($tmp, "shops/{$q['id']}/" . basename($q['logo_pfad']), $q['logo_pfad']);
                $this->ausfuehren('UPDATE shops SET logo_pfad = ? WHERE id = ?', [$q['logo_pfad'], $ziel]);
                $this->hinweise[] = 'Kanal: Shop-Logo aus der Quelle übernommen.';
            }
        }
    }

    private function zielNachTyp(?string $typ, $quellId): ?int
    {
        return match ($typ) {
            'kategorie'  => $this->ziel('kategorien', $quellId),
            'artikel'    => $this->ziel('artikel', $quellId),
            'hersteller' => $this->ziel('hersteller', $quellId),
            default      => $quellId === null ? null : (int)$quellId,
        };
    }

    // ── Dateien ─────────────────────────────────────────────────────────

    private function merkeKopie(string $tmp, string $imPaket, string $zielRelativ): void
    {
        $quelle = "$tmp/dateien/$imPaket";
        if (!is_file($quelle)) { $this->zaehle('dateien_fehlen'); return; }
        $this->kopieren[] = [$quelle, $this->public . '/' . ltrim($zielRelativ, '/')];
    }

    private array $kopiert = [];

    private function kopiereDateien(): void
    {
        foreach ($this->kopieren as [$von, $nach]) {
            if (file_exists($nach)) { $this->zaehle('dateien_schon_da'); continue; }
            if (!is_dir(dirname($nach)) && !mkdir(dirname($nach), 0755, true)) throw new RuntimeException("Ordner nicht anlegbar: " . dirname($nach));
            if (!copy($von, $nach)) throw new RuntimeException("Datei nicht kopierbar: $nach");
            $this->kopiert[] = $nach;
            $this->zaehle('dateien');
        }
    }

    private function raeumeKopienAuf(): void
    {
        foreach ($this->kopiert as $f) @unlink($f);
    }

    private function entferne(string $pfad): void
    {
        if (is_dir($pfad)) {
            foreach (array_diff(scandir($pfad), ['.', '..']) as $e) $this->entferne("$pfad/$e");
            @rmdir($pfad);
        } elseif (file_exists($pfad)) {
            @unlink($pfad);
        }
    }

    // ── DB-Helfer ───────────────────────────────────────────────────────

    /** Fügt eine Zeile ein — nur Spalten, die es im Ziel gibt, ohne id. */
    private function neu(string $tabelle, array $zeile, string $zaehler): int
    {
        $spalten = $this->spaltenCache[$tabelle] ??= $this->db->query("SHOW COLUMNS FROM `$tabelle`")->fetchAll(PDO::FETCH_COLUMN);
        $werte = array_intersect_key($zeile, array_flip($spalten));
        unset($werte['id']);
        $namen = array_keys($werte);
        $sql = "INSERT INTO `$tabelle` (`" . implode('`,`', $namen) . '`) VALUES (' . implode(',', array_fill(0, count($namen), '?')) . ')';
        $this->ausfuehren($sql, array_values($werte));
        $this->zaehle($zaehler);
        return (int)$this->db->lastInsertId();
    }

    private function ziel(string $art, $quellId): ?int
    {
        if ($quellId === null || $quellId === '') return null;
        return $this->map[$art][$quellId] ?? null;
    }

    private function wert(string $sql, array $p = [])
    {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st->fetchColumn();
    }

    private function zeile(string $sql, array $p = []): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function ausfuehren(string $sql, array $p = []): void
    {
        $this->db->prepare($sql)->execute($p);
    }

    private function zaehle(string $k): void
    {
        $this->zahlen[$k] = ($this->zahlen[$k] ?? 0) + 1;
    }
}
