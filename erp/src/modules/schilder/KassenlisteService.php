<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../dokumente/PdfGenerator.php';
require_once __DIR__ . '/../dokumente/BelegBilder.php';
require_once __DIR__ . '/../preise/PreisRepository.php';

/**
 * Kassen-Liste, Preisliste und Mini-Anhänger (Jacky 2026-11-19, Entwurf D:\ERP\kassenliste_mockup.svg).
 *
 * Anders als die Preisschilder (PreisschildService) wird hier NICHT zum Vater zusammengefasst:
 * jede verkaufbare Variante steht einzeln drin, mit eigener Artikelnummer und eigenem Barcode --
 * für Fachmieter-Kleinkram ohne EAN und Indra-Anhänger, die an der Kasse gescannt werden.
 *
 * Preis = Standardpreis (läuft eine Aktion, nimmt die Kasse beim Scannen ohnehin den Aktionspreis).
 * Barcode = EAN (GTIN13/GTIN8), sonst die Artikelnummer -- immer als Code 128, die Kasse findet beides.
 */
class KassenlisteService
{
    public const VORLAGEN = [
        'kassenliste' => ['name' => 'Kassen-Liste', 'symbol' => '🧾'],
        'preisliste'  => ['name' => 'Preisliste',   'symbol' => '📄'],
    ];

    private PDO $db;
    private PreisRepository $preisRepo;

    public function __construct()
    {
        $this->db        = Database::getInstance();
        $this->preisRepo = new PreisRepository();
    }

    /**
     * Verkaufbare Artikel nach Filter. Väter werden durch ihre Varianten ersetzt.
     *
     * @param array $f kategorie_id, hersteller_id, partner_id (int|0), nur_lagernd (bool), ids (int[] aus der Artikelliste)
     * @return array Zeilen: id, artikelnummer, name, gruppe, preis (float|null), code, bestand
     */
    public function artikel(array $f): array
    {
        $wo = ['a.aktiv = 1', 'a.ist_vater = 0', 'a.ist_konfigurierbar = 0', 'a.ist_gutschein = 0'];
        $p  = [];

        $ids = array_values(array_filter(array_map('intval', (array) ($f['ids'] ?? []))));
        if ($ids) {
            $in = implode(',', $ids);
            $wo[] = "(a.id IN ($in) OR a.vaterartikel_id IN ($in))";
        }
        // Varianten erben Hersteller/Partner/Kategorie im Zweifel vom Vater
        if (!empty($f['hersteller_id'])) { $wo[] = 'COALESCE(a.hersteller_id, v.hersteller_id) = ?'; $p[] = (int) $f['hersteller_id']; }
        if (!empty($f['partner_id']))    { $wo[] = 'COALESCE(a.partner_id, v.partner_id) = ?';       $p[] = (int) $f['partner_id']; }
        $katIds = !empty($f['kategorie_id']) ? $this->kategorieMitUnter((int) $f['kategorie_id']) : [];
        if ($katIds) {
            $kin = implode(',', $katIds);
            $wo[] = "EXISTS (SELECT 1 FROM artikel_kategorien ak WHERE ak.kategorie_id IN ($kin) AND ak.artikel_id IN (a.id, COALESCE(a.vaterartikel_id, a.id)))";
        }
        if (!$ids && empty($f['hersteller_id']) && empty($f['partner_id']) && !$katIds) return [];

        // Gruppe = Kategorie (bei Kategorie-Filter die passende Unterkategorie), sonst die erste des Artikels/Vaters
        $katWo = $katIds ? 'AND ak.kategorie_id IN (' . implode(',', $katIds) . ')' : '';
        $sql = "
            SELECT a.id, a.artikelnummer, a.name, a.vaterartikel_id, a.keine_lagerbestandsfuehrung,
                   COALESCE((SELECT k.name FROM artikel_kategorien ak JOIN kategorien k ON k.id = ak.kategorie_id
                             WHERE ak.artikel_id IN (a.id, COALESCE(a.vaterartikel_id, a.id)) $katWo
                             ORDER BY ak.artikel_id = a.id DESC, k.sortierung, k.name LIMIT 1), 'Ohne Kategorie') AS gruppe,
                   (SELECT c.code FROM artikel_codes c WHERE c.artikel_id = a.id AND c.typ IN ('GTIN13', 'GTIN8')
                    ORDER BY c.typ = 'GTIN13' DESC, c.id LIMIT 1) AS ean,
                   (SELECT COALESCE(SUM(lb.bestand), 0) FROM lagerbestand lb
                    JOIN lager l ON l.id = lb.lager_id AND l.typ <> 'messe' AND l.lager_beziehung <> 'haendler_aussenlager'
                    WHERE lb.artikel_id = a.id) AS bestand
            FROM artikel a
            LEFT JOIN artikel v ON v.id = a.vaterartikel_id
            WHERE " . implode(' AND ', $wo) . "
            ORDER BY gruppe, a.name, a.artikelnummer";
        $st = $this->db->prepare($sql);
        $st->execute($p);

        $erg = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $bestand = (float) $a['bestand'];
            if (!empty($f['nur_lagernd']) && !$a['keine_lagerbestandsfuehrung'] && $bestand <= 0) continue;
            $erg[] = [
                'id'            => (int) $a['id'],
                'artikelnummer' => $a['artikelnummer'],
                'name'          => $a['name'],
                'vater_id'      => (int) $a['vaterartikel_id'],
                'gruppe'        => $a['gruppe'],
                'preis'         => $this->standardPreis((int) $a['id'], (int) $a['vaterartikel_id']),
                'code'          => $a['ean'] ?: $a['artikelnummer'],
                'bestand'       => $a['keine_lagerbestandsfuehrung'] ? null : $bestand,
            ];
        }
        return $erg;
    }

    /**
     * PDF der Kassen-Liste bzw. Preisliste.
     * @param array $opt titel, untertitel, mit_bild, mit_barcode, gruppieren (bool)
     */
    public function listePdf(string $vorlage, array $ids, array $opt): string
    {
        if (!isset(self::VORLAGEN[$vorlage])) throw new InvalidArgumentException('Unbekannte Vorlage.');
        $zeilen = $ids ? $this->artikel(['ids' => $ids]) : [];
        // nur die angehakten, ohne die Varianten eines angehakten Vaters doppelt aufzunehmen
        $zeilen = array_values(array_filter($zeilen, fn($z) => in_array($z['id'], $ids, true)));
        if (!$zeilen) throw new RuntimeException('Keine Artikel ausgewählt.');

        $pdf      = new PdfGenerator();
        $barcode  = $vorlage === 'kassenliste' && !empty($opt['mit_barcode']);
        $mitBild  = !empty($opt['mit_bild']);
        foreach ($zeilen as &$z) {
            $z['barcode'] = $barcode ? $pdf->barcodeAlsBase64($z['code']) : '';
            $z['bild']    = $mitBild ? $this->bild($z['id'], $z['vater_id']) : '';
        }
        unset($z);

        $gruppen = [];
        foreach ($zeilen as $z) $gruppen[!empty($opt['gruppieren']) ? $z['gruppe'] : ''][] = $z;

        return $pdf->alsString('preisschild/kassenliste.html.twig', [
            'ueberschrift' => self::VORLAGEN[$vorlage]['name'],
            'kompakt'    => $vorlage === 'preisliste',
            'titel'      => (string) ($opt['titel'] ?? ''),
            'untertitel' => (string) ($opt['untertitel'] ?? ''),
            'datum'      => date('d.m.Y'),
            'anzahl'     => count($zeilen),
            'gruppen'    => $gruppen,
            'mit_bild'   => $mitBild,
            'mit_barcode'=> $barcode,
        ]);
    }

    /** Titel aus dem Filter, z.B. "Barbara Indra INDRA-DESIGN" bzw. "Kategorie Untersetzer · DROPS Design" */
    public function filterText(array $f): string
    {
        $teile = [];
        foreach (['partner_id' => 'partner', 'hersteller_id' => 'hersteller', 'kategorie_id' => 'kategorien'] as $k => $tab) {
            if (empty($f[$k])) continue;
            $st = $this->db->prepare("SELECT name FROM $tab WHERE id = ?");
            $st->execute([(int) $f[$k]]);
            if ($n = $st->fetchColumn()) $teile[] = $n;
        }
        return implode(' · ', $teile);
    }

    /** Auswahllisten für die Filter: [id => name] */
    public function partner(): array
    {
        return $this->db->query("SELECT p.id, p.name FROM partner p WHERE EXISTS (SELECT 1 FROM artikel a WHERE a.partner_id = p.id) ORDER BY p.name")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function hersteller(): array
    {
        return $this->db->query("SELECT id, name FROM hersteller ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** Kategorien als eingerückte Liste: [id => "— Unterkategorie"] */
    public function kategorien(): array
    {
        $alle = $this->db->query("SELECT id, parent_id, name FROM kategorien WHERE aktiv = 1 ORDER BY sortierung, name")->fetchAll(PDO::FETCH_ASSOC);
        $kinder = [];
        foreach ($alle as $k) $kinder[(int) $k['parent_id']][] = $k;
        $erg = [];
        $gehe = function (int $parent, int $tiefe) use (&$gehe, &$erg, $kinder) {
            foreach ($kinder[$parent] ?? [] as $k) {
                $erg[(int) $k['id']] = str_repeat('— ', $tiefe) . $k['name'];
                $gehe((int) $k['id'], $tiefe + 1);
            }
        };
        $gehe(0, 0);
        return $erg;
    }

    /** Kategorie samt allen Unterkategorien */
    private function kategorieMitUnter(int $id): array
    {
        $erg = [$id];
        $neu = [$id];
        while ($neu) {
            $neu = $this->db->query("SELECT id FROM kategorien WHERE parent_id IN (" . implode(',', $neu) . ")")->fetchAll(PDO::FETCH_COLUMN);
            $neu = array_values(array_diff(array_map('intval', $neu), $erg));
            $erg = array_merge($erg, $neu);
        }
        return $erg;
    }

    /** Standardpreis brutto; Varianten ohne eigenen Preis nehmen den des Vaters */
    private function standardPreis(int $id, int $vaterId): ?float
    {
        $p = $this->preisRepo->findStandardPreis($id);
        if (!$p && $vaterId) $p = $this->preisRepo->findStandardPreis($vaterId);
        return $p ? round((float) $p['brutto_vk'], 2) : null;
    }

    /** Erstes Bild der Variante, sonst des Vaters (klein, JPEG-Base64) */
    private function bild(int $id, int $vaterId): string
    {
        $st = $this->db->prepare("SELECT artikel_id, dateiname FROM artikel_bilder WHERE artikel_id IN (?, ?)
                                  ORDER BY artikel_id = ? DESC, position, id LIMIT 1");
        $st->execute([$id, $vaterId ?: $id, $id]);
        $b = $st->fetch(PDO::FETCH_ASSOC);
        $roh = $b ? BelegBilder::vorschau((int) $b['artikel_id'], $b['dateiname'], 200) : null;
        return $roh ? 'data:image/jpeg;base64,' . $roh : '';
    }
}
