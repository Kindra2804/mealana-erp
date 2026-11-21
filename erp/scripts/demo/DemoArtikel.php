<?php

require_once __DIR__ . '/DemoKatalog.php';
require_once __DIR__ . '/DemoBilder.php';

/**
 * Demo-Teil 2: Warengruppen, Kategorien, Hersteller/Lieferanten, Achsen und das Sortiment
 * (Garne mit Farben + Chargen, Stoffe als Meterware, Kurzwaren, Bücher) inkl. Preise, EAN,
 * Merkmale, Bilder, Shop-Zuordnung und Startbestand (Wareneingang vor gut einem Jahr).
 *
 * Für Teil 3 (Bewegungen) merkt sich der Teil alle verkaufbaren Artikel in $b->ids['verkauf'].
 */
class DemoArtikel
{
    private int $eanZaehler = 100000;
    private array $merkmalIds = [];
    private array $wertIds = [];

    public function __construct(private DemoBasis $b) {}

    public function erzeugen(): void
    {
        $this->warengruppen();
        $this->kategorien();
        $this->herstellerUndLieferanten();
        $this->achsen();
        $this->merkmaleLaden();

        foreach (DemoKatalog::garne() as $g)     $this->garn($g);
        foreach (DemoKatalog::stoffe() as $s)    $this->stoff($s);
        foreach (DemoKatalog::kurzwaren() as $k) $this->kurzware($k);
        foreach (DemoKatalog::buecher() as $bu)  $this->buch($bu);

        $n = (int)$this->b->db->query("SELECT COUNT(*) FROM artikel WHERE artikelnummer NOT IN ('99-9999')")->fetchColumn();
        $this->b->log("Artikel: $n (inkl. Varianten), " . count($this->b->ids['verkauf']) . ' verkaufbar');
    }

    // ── Grunddaten ────────────────────────────────────────────────────────

    private function warengruppen(): void
    {
        foreach ([['4010', 'Garne', 20], ['4020', 'Stoffe', 20], ['4030', 'Kurzwaren & Zubehör', 20], ['4040', 'Bücher', 10]] as $i => [$konto, $name, $ust]) {
            $this->b->ids['gruppe'][$name] = $this->b->ins('artikel_gruppen', [
                'konto_nr' => $konto, 'name' => $name, 'aktiv' => 1, 'an_kasse_waehlbar' => 1,
                'standard_steuer_prozent' => $ust, 'sortierung' => 10 + $i,
            ]);
        }
    }

    private function kategorien(): void
    {
        $sort = 0;
        foreach (DemoKatalog::KATEGORIEN as $haupt => $unter) {
            $hid = $this->kategorie($haupt, null, ++$sort);
            $this->b->ids['kat'][$haupt] = $hid;
            foreach ($unter as $i => $name) {
                $this->b->ids['kat'][$name] = $this->kategorie($name, $hid, $i + 1);
            }
        }
    }

    private function kategorie(string $name, ?int $parent, int $sort): int
    {
        $id = $this->b->ins('kategorien', [
            'parent_id' => $parent, 'name' => $name, 'sortierung' => $sort, 'aktiv' => 1,
            'beschreibung' => 'Entdecke unser Sortiment ' . $name . ' – sorgfältig ausgewählt für Handarbeit mit Freude.',
        ]);
        foreach ($this->b->ids['shops'] as $shopId) {
            $this->b->ins('kategorie_shops', ['kategorie_id' => $id, 'shop_id' => $shopId,
                'externe_kategorie_id' => (string)(100 + $id), 'synced_at' => date('Y-m-d H:i:s')]);
        }
        return $id;
    }

    private function herstellerUndLieferanten(): void
    {
        $kred = 30001;
        foreach (DemoKatalog::HERSTELLER as $key => $h) {
            $slug = strtolower(preg_replace('/[^a-z]/i', '', $h['name']));
            $this->b->ids['hersteller'][$key] = $this->b->ins('hersteller', [
                'name' => $h['name'], 'handelsname' => $h['name'], 'land' => $h['land'], 'strasse' => $h['strasse'],
                'plz' => $h['plz'], 'ort' => $h['ort'], 'email' => "info@$slug.example", 'webseite' => "https://$slug.example", 'aktiv' => 1,
            ]);
            $this->b->ids['lieferant'][$key] = $this->b->ins('lieferanten', [
                'name' => $h['name'], 'firma' => $h['name'] . ' GmbH', 'land' => $h['land'], 'strasse' => $h['strasse'],
                'plz' => $h['plz'], 'ort' => $h['ort'], 'kundennummer' => 'K-' . mt_rand(10000, 99999), 'kreditorennummer' => (string)$kred++,
                'ustid' => $h['land'] === 'AT' ? 'ATU' . mt_rand(10000000, 99999999) : 'DE' . mt_rand(100000000, 999999999),
                'steuerregel' => $h['land'] === 'AT' ? 'inland' : 'eu_igl', 'waehrung' => 'EUR', 'zahlungsziel_tage' => 30,
                'skonto_prozent' => 2, 'skonto_tage' => 10, 'mindestbestellwert' => 150, 'standard_lieferkosten' => 9.90, 'lieferzeit_tage' => 7,
                'email' => "bestellung@$slug.example", 'telefon' => '+49 000 000000', 'aktiv' => 1,
            ]);
        }
    }

    private function achsen(): void
    {
        $this->b->ids['achse']['farbe'] = $this->b->ins('varianten_achsen', [
            'name' => 'Farbe', 'code' => 'farbe', 'darstellungsform' => 'swatches', 'ist_gruppe' => 0, 'im_kindnamen_anzeigen' => 1, 'sort_order' => 10]);
        $this->b->ids['achse']['staerke'] = $this->b->ins('varianten_achsen', [
            'name' => 'Stärke', 'code' => 'staerke', 'darstellungsform' => 'dropdown', 'ist_gruppe' => 0, 'im_kindnamen_anzeigen' => 1, 'sort_order' => 20]);
    }

    private function merkmaleLaden(): void
    {
        foreach ($this->b->db->query("SELECT id, slug FROM merkmale") as $m) $this->merkmalIds[$m['slug']] = (int)$m['id'];
        foreach ($this->b->db->query("SELECT id, symbol FROM merkmal_werte WHERE symbol IS NOT NULL") as $w) $this->wertIds[$w['symbol']] = (int)$w['id'];
    }

    /** Wert-ID eines Text-Merkmals (legt den Wert bei Bedarf an) */
    private function textWert(string $slug, string $text): int
    {
        $key = $slug . '|' . $text;
        if (!isset($this->wertIds[$key])) {
            $this->wertIds[$key] = $this->b->ins('merkmal_werte', ['merkmal_id' => $this->merkmalIds[$slug], 'wert' => $text, 'sort_order' => 0]);
        }
        return $this->wertIds[$key];
    }

    // ── Sortiment ─────────────────────────────────────────────────────────

    private function garn(array $g): void
    {
        $basis = [
            'hersteller_id' => $this->b->ids['hersteller'][$g['herst']], 'steuerklasse_id' => 1,
            'artikel_gruppe_id' => $this->b->ids['gruppe']['Garne'], 'artikeltyp_id' => 1, 'einheit_id' => 1,
            'inhalt_menge' => $g['gr'], 'inhalt_einheit' => 'g', 'grundpreis_bezugsmenge' => 100, 'grundpreis_anzeigen' => 1,
            'gewicht_artikel' => $g['gr'] / 1000, 'versandklasse_id' => 1, 'herkunftsland' => 'AT', 'taric_code' => '51091090',
            'charge_pflicht' => 1, 'meldebestand' => 5,
            'kurzbeschreibung' => $g['zus'] . ' · ' . $g['gr'] . ' g = ' . $g['m'] . ' m · Nadel ' . $g['nadel'] . ' mm',
            'beschreibung' => '<p><strong>' . $g['name'] . '</strong> – ' . $g['zus'] . '.</p><p>Lauflänge ' . $g['m'] . ' m je ' . $g['gr']
                . ' g, empfohlene Nadelstärke ' . $g['nadel'] . ' mm. Ideal für ' . ($g['kat'] === 'Sockenwolle' ? 'Socken, Handschuhe und kleine Projekte' : 'Pullover, Tücher und Accessoires') . '.</p>',
        ];
        $merkmale = [
            $this->textWert('wollqualitaet', $g['quali']), $this->textWert('zusammensetzung', $g['zus']),
            $this->textWert('gewicht_laenge', $g['gr'] . ' g = ' . $g['m'] . ' m'), $this->textWert('nadelstaerke', $g['nadel'] . ' mm'),
            $this->wertIds[$g['lb']], $this->wertIds[$g['mp']], $this->wertIds[$g['gg']],
        ];
        foreach ($g['auszeichnung'] ?? [] as $a) $merkmale[] = $this->wertIds[$a];
        foreach ($g['pflege'] as $p) $merkmale[] = $this->wertIds[$p];

        $this->vaterMitKindern($g, $basis, 'farbe', $g['farben'], fn($farbe, $hex) => $g['vk'], $g['ek'],
            $this->b->ids['kat'][$g['kat']], $g['shops'], $merkmale, 'garn', $g['muster'] ?? 'uni', 'Knäuel');
    }

    private function stoff(array $s): void
    {
        $basis = [
            'hersteller_id' => $this->b->ids['hersteller'][$s['herst']], 'steuerklasse_id' => 1,
            'artikel_gruppe_id' => $this->b->ids['gruppe']['Stoffe'], 'artikeltyp_id' => 3, 'einheit_id' => 2,
            'inhalt_menge' => 0.01, 'inhalt_einheit' => 'm', 'grundpreis_bezugsmenge' => 1, 'grundpreis_anzeigen' => 1,
            'gewicht_artikel' => round($s['gsm'] * $s['breite'] / 100 / 100000, 3), 'versandklasse_id' => 1, 'herkunftsland' => 'DE',
            'taric_code' => '52083100', 'charge_pflicht' => 0, 'meldebestand' => 300,
            'kurzbeschreibung' => $s['zus'] . ' · ' . $s['breite'] . ' cm breit · ' . $s['gsm'] . ' g/m² · Preis je 10 cm ab 20 cm',
            'beschreibung' => '<p><strong>' . $s['name'] . '</strong> – ' . $s['zus'] . '.</p><p>Breite ' . $s['breite'] . ' cm, Gewicht '
                . $s['gsm'] . ' g/m². Wird am Stück geschnitten: Mindestabnahme 20 cm, danach in 10-cm-Schritten.</p>',
        ];
        $merkmale = [$this->textWert('zusammensetzung', $s['zus']), $this->wertIds['wasch40'], $this->wertIds['buegeln2']];
        // Preis je cm (1 Stück = 1 cm)
        $this->vaterMitKindern($s, $basis, 'farbe', $s['farben'], fn() => round($s['vk'] / 100, 2), round($s['ek'] / 100, 2),
            $this->b->ids['kat'][$s['kat']], [2], $merkmale, 'stoff', $s['muster'], 'cm');
    }

    private function kurzware(array $k): void
    {
        $istNadel = isset($k['staerken']);
        $basis = [
            'hersteller_id' => $this->b->ids['hersteller'][$k['herst']], 'steuerklasse_id' => 1,
            'artikel_gruppe_id' => $this->b->ids['gruppe']['Kurzwaren & Zubehör'], 'artikeltyp_id' => $istNadel ? 2 : 6, 'einheit_id' => 4,
            'gewicht_artikel' => 0.05, 'versandklasse_id' => 1, 'herkunftsland' => 'DE', 'charge_pflicht' => 0, 'meldebestand' => 3,
            'kurzbeschreibung' => $k['name'] . ' – bewährte Qualität für Handarbeit.',
            'beschreibung' => '<p>' . $k['name'] . ' von ' . DemoKatalog::HERSTELLER[$k['herst']]['name'] . '.</p>',
        ];
        $shops = [1, 2];
        if ($istNadel) {
            $staerken = array_combine(array_keys($k['staerken']), array_fill(0, count($k['staerken']), $k['farbe']));
            $this->vaterMitKindern($k, $basis, 'staerke', $staerken, fn($wert) => $k['staerken'][$wert], null,
                $this->b->ids['kat'][$k['kat']], $shops, [], $k['bild'], 'uni', 'Stk', $k['ek_faktor']);
        } else {
            $this->einzelartikel($k, $basis, $k['vk'], $k['ek'], $this->b->ids['kat'][$k['kat']], $shops, $k['bild'], $k['farbe']);
        }
    }

    private function buch(array $bu): void
    {
        $basis = [
            'hersteller_id' => $this->b->ids['hersteller']['verlag'], 'steuerklasse_id' => 2,
            'artikel_gruppe_id' => $this->b->ids['gruppe']['Bücher'], 'artikeltyp_id' => 6, 'einheit_id' => 4,
            'gewicht_artikel' => 0.6, 'versandklasse_id' => 1, 'herkunftsland' => 'AT', 'taric_code' => '49019900', 'charge_pflicht' => 0, 'meldebestand' => 2,
            'kurzbeschreibung' => 'Hardcover, ca. 160 Seiten, mit vielen Anleitungen und Schritt-für-Schritt-Fotos.',
            'beschreibung' => '<p><strong>' . $bu['name'] . '</strong> – das Praxisbuch aus dem ' . DemoKatalog::HERSTELLER['verlag']['name'] . '.</p>',
        ];
        $this->einzelartikel($bu + ['herst' => 'verlag'], $basis, $bu['vk'], $bu['ek'], $this->b->ids['kat']['Bücher & Anleitungen'], [1], 'buch', $bu['farbe']);
    }

    // ── Bausteine ─────────────────────────────────────────────────────────

    /**
     * Vater + Kinder über eine Achse. $werte = [Wert => Hex], $preis(wert, hex) = VK brutto je Einheit,
     * $ek = EK netto (oder null → $ekFaktor × VK netto).
     */
    private function vaterMitKindern(array $d, array $basis, string $achse, array $werte, callable $preis, ?float $ek,
                                     int $kat, array $shops, array $merkmale, string $bildArt, string $muster, string $einheitText,
                                     float $ekFaktor = 0.45): void
    {
        $angelegt = $this->b->vorTagen(mt_rand(400, 700));
        // Vaterbild: eine kräftige Farbe aus der Mitte der Palette (die erste ist oft Weiß/Natur)
        $ersterHex = array_values($werte)[intdiv(count($werte), 2)];
        $vaterVk = $preis(array_key_first($werte), $ersterHex);
        $vaterId = $this->artikel($basis + ['artikelnummer' => $d['nr'], 'name' => $d['name'], 'ist_vater' => 1, 'erstellt_am' => $angelegt]);
        $this->zuordnen($vaterId, $kat, $shops, $merkmale);
        $this->preis($vaterId, $vaterVk, (int)$basis['steuerklasse_id']);
        DemoBilder::speichern($this->b, $vaterId, $bildArt, $ersterHex, $muster, $d['name']);
        $this->b->ins('artikel_achsen', ['artikel_id' => $vaterId, 'achse_id' => $this->b->ids['achse'][$achse], 'sort_order' => 0]);

        $i = 0;
        foreach ($werte as $wert => $hex) {
            $i++;
            $code = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $wertText = $achse === 'farbe' ? $code . ' ' . $wert : $wert;
            $wertId = $this->b->ins('varianten_achse_werte', [
                'artikel_id' => $vaterId, 'achse_id' => $this->b->ids['achse'][$achse], 'wert' => $wertText,
                'wert_zusatz' => $achse === 'farbe' ? $hex : null, 'sort_order' => $i,
            ]);
            $vk = $preis($wert, $hex);
            $kindId = $this->artikel($basis + [
                'artikelnummer' => $d['nr'] . '-' . $code, 'name' => $d['name'] . ' ' . $wertText,
                'vaterartikel_id' => $vaterId, 'erstellt_am' => $angelegt,
            ]);
            $this->b->ins('varianten_kombination_werte', ['kombination_id' => $kindId, 'wert_id' => $wertId]);
            $this->zuordnen($kindId, $kat, $shops, $merkmale);
            $this->preis($kindId, $vk, (int)$basis['steuerklasse_id']);
            $this->ean($kindId);
            $kindEk = $ek ?? round($vk / ($basis['steuerklasse_id'] === 2 ? 1.1 : 1.2) * $ekFaktor, 2);
            $this->lieferant($kindId, $d['herst'], $d['nr'] . '-' . $code, $kindEk);
            DemoBilder::speichern($this->b, $kindId, $bildArt, $hex, $muster, $d['name'] . ' ' . $wert);
            $this->startbestand($kindId, $basis, $d['renner'] ?? 1, $kindEk, $d['herst'], $angelegt);
            $this->merkeVerkaufbar($kindId, $d, $basis, $vk, $kindEk, $shops, $d['name'] . ' ' . $wertText, $einheitText);
        }
    }

    private function einzelartikel(array $d, array $basis, float $vk, float $ek, int $kat, array $shops, string $bildArt, string $hex): void
    {
        $angelegt = $this->b->vorTagen(mt_rand(400, 700));
        $id = $this->artikel($basis + ['artikelnummer' => $d['nr'], 'name' => $d['name'], 'erstellt_am' => $angelegt]);
        $this->zuordnen($id, $kat, $shops, []);
        $this->preis($id, $vk, (int)$basis['steuerklasse_id']);
        $this->ean($id);
        $this->lieferant($id, $d['herst'], $d['nr'], $ek);
        DemoBilder::speichern($this->b, $id, $bildArt, $hex, 'uni', $d['name']);
        $this->startbestand($id, $basis, $d['renner'] ?? 1, $ek, $d['herst'], $angelegt);
        $this->merkeVerkaufbar($id, $d, $basis, $vk, $ek, $shops, $d['name'], 'Stk');
    }

    private function artikel(array $werte): int
    {
        $werte += ['aktiv' => 1, 'zustand' => 'neu', 'hat_eigenen_lagerstand' => 1, 'preise_vererben' => 0,
                   'ist_vater' => 0, 'vaterartikel_id' => null];
        $werte['url_slug'] = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(strtr($werte['name'],
            ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue']))), '-');
        $werte['meta_titel'] = mb_substr($werte['name'] . ' | Garnstube', 0, 70);
        $werte['meta_description'] = mb_substr(strip_tags($werte['kurzbeschreibung'] ?? ''), 0, 160);
        return $this->b->ins('artikel', $werte);
    }

    private function zuordnen(int $id, int $kat, array $shops, array $merkmalWerte): void
    {
        $this->b->ins('artikel_kategorien', ['artikel_id' => $id, 'kategorie_id' => $kat]);
        foreach ($shops as $shopId) {
            $this->b->ins('artikel_shops', ['artikel_id' => $id, 'shop_id' => $shopId, 'aktiv' => 1,
                'external_id' => (string)(5000 + $id), 'sync_status' => 'synced', 'synced_at' => date('Y-m-d H:i:s')]);
        }
        $merkmalVon = $this->b->db->prepare("SELECT merkmal_id FROM merkmal_werte WHERE id = ?");
        foreach ($merkmalWerte as $wid) {
            $merkmalVon->execute([$wid]);
            $this->b->ins('artikel_merkmale', ['artikel_id' => $id, 'merkmal_id' => (int)$merkmalVon->fetchColumn(), 'merkmal_wert_id' => $wid]);
        }
    }

    private function preis(int $id, float $brutto, int $steuerklasse): void
    {
        $satz = $steuerklasse === 2 ? 1.10 : 1.20;
        $this->b->ins('artikel_preise', ['artikel_id' => $id, 'kundengruppen_id' => 1,
            'brutto_vk' => $brutto, 'netto_vk' => round($brutto / $satz, 4)]);
    }

    /** EAN-13 aus dem Präfix 200 (für interne Nummern reserviert → nie ein echtes Produkt) */
    private function ean(int $id): void
    {
        $basis = '200' . str_pad((string)$this->eanZaehler++, 9, '0', STR_PAD_LEFT);
        $summe = 0;
        foreach (str_split($basis) as $i => $z) $summe += (int)$z * ($i % 2 ? 3 : 1);
        $this->b->ins('artikel_codes', ['artikel_id' => $id, 'code' => $basis . ((10 - $summe % 10) % 10), 'typ' => 'GTIN13']);
    }

    private function lieferant(int $id, string $herst, string $nr, float $ek): void
    {
        $this->b->ins('artikel_lieferanten', ['artikel_id' => $id, 'lieferant_id' => $this->b->ids['lieferant'][$herst],
            'artikelnummer_lieferant' => 'L-' . $nr, 'netto_ek' => $ek, 'waehrung' => 'EUR', 'vpe_menge' => 1,
            'lieferzeit_tage' => 7, 'standard_lieferant' => 1, 'aktiv' => 1]);
    }

    /**
     * Startbestand: Wareneingang vor ca. 13 Monaten ins Zentrallager (Garne mit Charge), ein Teil
     * ins Ladengeschäft. Teil 3 bucht die Verkäufe davon ab.
     */
    private function startbestand(int $id, array $basis, int $renner, float $ek, string $herst, string $datum): void
    {
        $istStoff = $basis['artikeltyp_id'] === 3;
        $menge = $istStoff ? mt_rand(30, 60) * 100 * $renner : mt_rand(25, 45) * $renner;
        $charge = $basis['charge_pflicht'] ? 'C' . mt_rand(1000, 9899) : null;
        $anteilLaden = $istStoff ? 0.3 : 0.25;
        foreach (['zentral' => 1 - $anteilLaden, 'laden' => $anteilLaden] as $lager => $anteil) {
            $m = (int)round($menge * $anteil);
            if ($m <= 0) continue;
            $lagerId = $this->b->ids['lager'][$lager];
            $this->b->ins('lagerbestand', ['artikel_id' => $id, 'lager_id' => $lagerId, 'charge' => $charge,
                'charge_status' => $charge ? 'erfasst' : 'unbekannt', 'bestand' => $m]);
            $this->b->ins('lager_bewegungen', ['artikel_id' => $id, 'lager_id' => $lagerId, 'lieferant_id' => $this->b->ids['lieferant'][$herst],
                'ek_preis' => $ek, 'charge' => $charge, 'bewegungstyp' => 'eingang', 'menge' => $m, 'bestand_vorher' => 0, 'bestand_nachher' => $m,
                'referenz' => 'Erstbestand', 'notiz' => 'Startbestand', 'erstellt_am' => $datum, 'benutzer_id' => $this->b->demoBenutzerId]);
        }
    }

    private function merkeVerkaufbar(int $id, array $d, array $basis, float $vk, float $ek, array $shops, string $name, string $einheit): void
    {
        $this->b->ids['verkauf'][] = [
            'id' => $id, 'nr' => $this->b->db->query("SELECT artikelnummer FROM artikel WHERE id = $id")->fetchColumn(),
            'name' => $name, 'vk' => $vk, 'ek' => $ek, 'steuer' => $basis['steuerklasse_id'] === 2 ? 10.0 : 20.0,
            'typ' => (int)$basis['artikeltyp_id'], 'charge_pflicht' => (int)$basis['charge_pflicht'],
            'renner' => $d['renner'] ?? 1, 'saison' => $d['saison'] ?? null, 'shops' => $shops, 'einheit' => $einheit,
            'gruppe' => (int)$basis['artikel_gruppe_id'],
        ];
    }
}
