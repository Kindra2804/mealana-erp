<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/QrCode.php';
require_once __DIR__ . '/../dokumente/PdfGenerator.php';
require_once __DIR__ . '/../preise/PreisService.php';
require_once __DIR__ . '/../preise/PreisRepository.php';
require_once __DIR__ . '/../dokumente/BelegBilder.php';
require_once __DIR__ . '/PflegeSymbole.php';

/**
 * PreisschildService – Preisschilder aus Artikeldaten (Jacky + Babsi 2026-11-12, Entwurf D:\ERP\preisschilder_mockup.svg).
 *
 * Vorlage "messe": 8 Schilder je A4 (2 x 4, je 95 x 60 mm, Rand für den Drucker), eines je Garnqualität:
 *   Name, Hersteller, Zusammensetzung, Gewicht/Lauflänge, Nadelstärke (kleinste–größte), Preis,
 *   Grundpreis (Garn immer je kg, wie im Shop), optional "statt"-Preis (Aktion / Messepreis) und QR zum Shop.
 *
 * - Kind-Artikel werden zu ihrem Vater zusammengefasst (ein Schild je Qualität, nicht je Farbe).
 * - Preis = Effektivpreis der Standard-Kundengruppe (Aktion/SALE zählt). Messepreis: x % auf den
 *   Standardpreis, sofern das günstiger ist als ein laufender Aktionspreis.
 * - Haben die Farben verschiedene Preise, steht "ab" vor dem Preis.
 * - Jeder Druck wird in preisschild_drucke protokolliert (für "Schilder veraltet").
 *
 * Vorlage "laden": gleiches Raster, dazu Hausfarben-Streifen, optional Bild, Maschenprobe, Pflegesymbole
 *   und Badges: AKTION -x % (mit "statt" und "bis"), AUSLAUF, LETZTE CHANCE (Auslauf + Bestand <= 3),
 *   NEU (angelegt vor < 60 Tagen), BIO / GOTS / HANDGEFÄRBT (Merkmal "Auszeichnung").
 *
 * Merkmale werden über ihren slug gelesen (Migration 233).
 */
class PreisschildService
{
    public const VORLAGEN = [
        'messe' => ['name' => 'Messe-Schild', 'je_seite' => 8],
        'laden' => ['name' => 'Laden-Schild', 'je_seite' => 8],
    ];
    /** Hausfarben für den Streifen am Laden-Schild */
    public const HAUSFARBEN = [
        'mealana' => ['MeaLana-Blau', '#1d4076'],
        'sow'     => ['Sockenwolle-Weinrot', '#8e3438'],
        'bio'     => ['bio-wolle-Grün', '#3b6b52'],
        'grau'    => ['Neutral (grau)', '#555555'],
    ];
    private const NEU_TAGE = 60;
    private const LETZTE_CHANCE_BIS = 3;
    private const BADGE_FARBEN = ['aktion' => '#c0392b', 'auslauf' => '#e67e22', 'letzte' => '#8e1b1b', 'neu' => '#1f7a8c',
                                  'bio' => '#3b6b52', 'gots' => '#3b6b52', 'handgefaerbt' => '#7d3c98'];

    private PDO $db;
    private PreisService $preise;
    private PreisRepository $preisRepo;
    private int $standardKg;

    public function __construct()
    {
        $this->db         = Database::getInstance();
        $this->preise     = new PreisService();
        $this->preisRepo  = new PreisRepository();
        $this->standardKg = (int) $this->db->query("SELECT id FROM kundengruppen WHERE ist_standard = 1 LIMIT 1")->fetchColumn();
    }

    /**
     * Fasst Artikel-IDs zu Schild-Artikeln zusammen: Kind -> Vater, Reihenfolge bleibt, keine Doppelten.
     * Konfigurierbare Layouts (Schilder-Konfigurator) werden übersprungen.
     * @return int[]
     */
    public function schildArtikel(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $in = implode(',', $ids);
        $zeilen = $this->db->query("SELECT id, vaterartikel_id, ist_konfigurierbar FROM artikel WHERE id IN ($in)")->fetchAll(PDO::FETCH_UNIQUE);
        $erg = [];
        foreach ($ids as $id) {
            $a = $zeilen[$id] ?? null;
            if (!$a || $a['ist_konfigurierbar']) continue;
            $ziel = (int) ($a['vaterartikel_id'] ?: $id);
            $erg[$ziel] = $ziel;
        }
        return array_values($erg);
    }

    /** Artikel einer Messe-Umbuchung (Sync-Paket), in der Reihenfolge des Scannens */
    public function artikelAusMesse(int $syncId): array
    {
        $st = $this->db->prepare("SELECT artikel_id FROM kassen_messe_umbuchungen WHERE sync_id = ? ORDER BY id");
        $st->execute([$syncId]);
        return $this->schildArtikel($st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Liste für die Auswahlseite: id, artikelnummer, name, hersteller, preis, zuletzt gedruckt
     */
    public function uebersicht(array $schildIds, string $vorlage): array
    {
        $erg = [];
        foreach ($schildIds as $id) {
            $d = $this->daten($id, ['vorlage' => $vorlage]);
            if (!$d) continue;
            $st = $this->db->prepare("SELECT preis, statt_preis, gedruckt_am FROM preisschild_drucke
                                      WHERE artikel_id = ? AND vorlage = ? ORDER BY gedruckt_am DESC, id DESC LIMIT 1");
            $st->execute([$id, $vorlage]);
            $d['letzter_druck'] = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            $erg[] = $d;
        }
        return $erg;
    }

    /**
     * @param array $opt start (1..8), schnitt (bool), messe_prozent (float), qr_shop_id (int|null)
     */
    public function pdf(string $vorlage, array $schildIds, array $opt, ?int $benutzerId): string
    {
        if (!isset(self::VORLAGEN[$vorlage])) throw new InvalidArgumentException('Unbekannte Vorlage.');
        $opt['vorlage'] = $vorlage;
        if ($vorlage !== 'messe') $opt['messe_prozent'] = 0;
        $jeSeite = self::VORLAGEN[$vorlage]['je_seite'];

        $schilder = [];
        foreach ($schildIds as $id) {
            $d = $this->daten((int) $id, $opt);
            if ($d) $schilder[] = $d;
        }
        if (!$schilder) throw new RuntimeException('Keine passenden Artikel gefunden.');

        $start = max(1, min($jeSeite, (int) ($opt['start'] ?? 1)));
        $felder = array_merge(array_fill(0, $start - 1, null), $schilder);
        while (count($felder) % $jeSeite) $felder[] = null;

        $pdf = (new PdfGenerator())->alsString("preisschild/$vorlage.html.twig", [
            'seiten'  => array_chunk($felder, $jeSeite),
            'schnitt' => !empty($opt['schnitt']),
            'farbe'   => (self::HAUSFARBEN[$opt['farbe'] ?? ''] ?? self::HAUSFARBEN['mealana'])[1],
        ]);

        $log = $this->db->prepare("INSERT INTO preisschild_drucke (artikel_id, vorlage, preis, statt_preis, merkmale, benutzer_id) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($schilder as $s) {
            $log->execute([$s['id'], $vorlage, $s['preis'], $s['statt'], json_encode($s['protokoll'], JSON_UNESCAPED_UNICODE), $benutzerId]);
        }
        return $pdf;
    }

    /** Alle Daten eines Schilds; null, wenn es den Artikel nicht gibt */
    public function daten(int $id, array $opt): ?array
    {
        $st = $this->db->prepare("
            SELECT a.id, a.artikelnummer, a.name, a.inhalt_menge, a.inhalt_einheit, a.grundpreis_bezugsmenge,
                   a.grundpreis_anzeigen, a.ist_auslaufartikel, a.erstellt_am, t.code AS typ, h.name AS hersteller
            FROM artikel a
            JOIN artikel_typen t ON t.id = a.artikeltyp_id
            LEFT JOIN hersteller h ON h.id = a.hersteller_id
            WHERE a.id = ?");
        $st->execute([$id]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a) return null;

        $m = $this->merkmale($id);
        [$preis, $statt, $ab, $bis] = $this->preis($id, (float) ($opt['messe_prozent'] ?? 0));

        $titel = $a['name'];
        $d = [
            'id'              => $id,
            'artikelnummer'   => $a['artikelnummer'],
            'titel'           => $titel,
            'titel_klasse'    => mb_strlen($titel) > 30 ? 'klein' : (mb_strlen($titel) > 20 ? 'mittel' : ''),
            'hersteller'      => $a['hersteller'] ?? '',
            'zusammensetzung' => $m['zusammensetzung'][0] ?? '',
            'lauflaenge'      => $this->lauflaengeText($m['gewicht_laenge'][0] ?? ''),
            'nadel'           => $this->nadelText($m['nadelstaerke'] ?? []),
            'preis'           => $preis,
            'statt'           => $statt,
            'ab'              => $ab,
            'grundpreis'      => $preis !== null ? $this->grundpreisText($a, $id, $preis) : '',
            'qr'              => !empty($opt['qr_shop_id']) ? $this->qr($id, (int) $opt['qr_shop_id']) : '',
        ];
        $d['protokoll'] = ['zusammensetzung' => $d['zusammensetzung'], 'lauflaenge' => $d['lauflaenge'],
                           'nadel' => $d['nadel'], 'grundpreis' => $d['grundpreis']];

        if (($opt['vorlage'] ?? '') === 'laden' || !empty($opt['mit_badges'])) {
            $symbole = $this->symbole($id);
            $d['badges']      = $this->badges($a, $id, $preis, $statt, $symbole['auszeichnung'] ?? []);
            $d['aktion_bis']  = $statt && $bis ? date('d.m.Y', strtotime($bis)) : '';
            $d['lauf_kurz']   = preg_replace('/\s*=\s*/u', ' / ', $m['gewicht_laenge'][0] ?? '');
            $d['nadel_mm']    = $d['nadel'] ? preg_replace('/^Nadelstärke /u', '', $d['nadel']) . ' mm' : '';
            $d['maschen']     = $this->maschenText($m['maschenprobe'][0] ?? '');
            $d['pflege']      = array_values(array_filter(array_map([PflegeSymbole::class, 'dataUri'], $symbole['pflege'] ?? [])));
            $d['bild']        = !empty($opt['mit_bild']) ? $this->bild($id) : '';
            $d['protokoll']['badges'] = array_column($d['badges'], 'text');
            // Titel neben den Badges hat nur 56 mm, ohne Badges 85 mm (15 / 12,5 / 10,5 pt)
            $len = mb_strlen($titel); [$g1, $g2] = $d['badges'] ? [15, 20] : [24, 30];
            $d['titel_klasse'] = $len > $g2 ? 'klein' : ($len > $g1 ? 'mittel' : '');
        }
        return $d;
    }

    /** slug => [werte] aller aktiven Merkmale eines Artikels */
    private function merkmale(int $id): array
    {
        $st = $this->db->prepare("
            SELECT m.slug, w.wert FROM artikel_merkmale am
            JOIN merkmale m ON m.id = am.merkmal_id AND m.aktiv = 1
            JOIN merkmal_werte w ON w.id = am.merkmal_wert_id
            WHERE am.artikel_id = ? ORDER BY w.sort_order, w.wert");
        $st->execute([$id]);
        $erg = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $z) $erg[$z['slug']][] = $z['wert'];
        return $erg;
    }

    /**
     * [preis, statt, ab]: Effektivpreis (Aktion zählt) bzw. Messepreis; statt = Standardpreis, wenn günstiger.
     * Vater ohne eigenen Preis: günstigste Farbe, "ab" wenn die Farben verschieden kosten.
     */
    private function preis(int $id, float $messeProzent): array
    {
        $kinder = $this->db->prepare("SELECT id FROM artikel WHERE vaterartikel_id = ? AND aktiv = 1");
        $kinder->execute([$id]);
        $kandidaten = array_merge([$id], $kinder->fetchAll(PDO::FETCH_COLUMN));

        $preise = [];
        foreach ($kandidaten as $k) {
            $eff = $this->preise->getEffektiverPreis((int) $k, $this->standardKg);
            if ($eff['brutto_vk'] === null) continue;
            $std = $this->preisRepo->findStandardPreis((int) $k);
            $preise[(int) $k] = ['eff' => (float) $eff['brutto_vk'], 'std' => $std ? (float) $std['brutto_vk'] : (float) $eff['brutto_vk'], 'bis' => $eff['bis'] ?? null];
        }
        if (!$preise) return [null, null, false, null];

        // Vater-eigener Preis hat Vorrang, sonst die günstigste Farbe
        $basis = $preise[$id] ?? null;
        if (!$basis) {
            uasort($preise, fn($x, $y) => $x['eff'] <=> $y['eff']);
            $basis = reset($preise);
        }
        $ab = count(array_unique(array_map(fn($p) => round($p['eff'], 2), $preise))) > 1;

        $preis = $basis['eff'];
        if ($messeProzent > 0) $preis = min($preis, round($basis['std'] * (1 - $messeProzent / 100), 2));
        $statt = $preis < $basis['std'] - 0.004 ? $basis['std'] : null;
        return [round($preis, 2), $statt, $ab, $basis['bis']];
    }

    /** slug => [symbol-Schlüssel] für Merkmale mit Symbol (Pflege, Auszeichnung) */
    private function symbole(int $id): array
    {
        $st = $this->db->prepare("
            SELECT m.slug, w.symbol FROM artikel_merkmale am
            JOIN merkmale m ON m.id = am.merkmal_id AND m.aktiv = 1 AND m.slug IN ('pflege', 'auszeichnung')
            JOIN merkmal_werte w ON w.id = am.merkmal_wert_id
            WHERE am.artikel_id = ? AND w.symbol IS NOT NULL ORDER BY w.sort_order");
        $st->execute([$id]);
        $erg = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $z) $erg[$z['slug']][] = $z['symbol'];
        return $erg;
    }

    /** Badges in Anzeige-Reihenfolge: [text, farbe, rahmen(bool)] */
    private function badges(array $a, int $id, ?float $preis, ?float $statt, array $auszeichnung): array
    {
        $b = [];
        if ($statt && $preis !== null) {
            $b[] = ['text' => 'AKTION −' . round((1 - $preis / $statt) * 100) . ' %', 'farbe' => self::BADGE_FARBEN['aktion'], 'rahmen' => false];
        }
        if ($this->istAuslauf($a, $id)) {
            $letzte = $this->bestand($id) <= self::LETZTE_CHANCE_BIS;
            $b[] = $letzte ? ['text' => 'LETZTE CHANCE', 'farbe' => self::BADGE_FARBEN['letzte'], 'rahmen' => false]
                           : ['text' => 'AUSLAUF', 'farbe' => self::BADGE_FARBEN['auslauf'], 'rahmen' => false];
        } elseif ($a['erstellt_am'] && strtotime($a['erstellt_am']) >= strtotime('-' . self::NEU_TAGE . ' days')) {
            $b[] = ['text' => 'NEU', 'farbe' => self::BADGE_FARBEN['neu'], 'rahmen' => false];
        }
        foreach (['handgefaerbt' => 'HANDGEFÄRBT', 'bio' => 'BIO', 'gots' => 'GOTS'] as $sym => $text) {
            if (in_array($sym, $auszeichnung, true)) $b[] = ['text' => $text, 'farbe' => self::BADGE_FARBEN[$sym], 'rahmen' => $sym === 'gots'];
        }
        return $b;
    }

    /** Auslauf: der Artikel selbst, oder alle aktiven Farben eines Vaters */
    private function istAuslauf(array $a, int $id): bool
    {
        if ($a['ist_auslaufartikel']) return true;
        $st = $this->db->prepare("SELECT COUNT(*) AS n, SUM(ist_auslaufartikel) AS a FROM artikel WHERE vaterartikel_id = ? AND aktiv = 1");
        $st->execute([$id]);
        $k = $st->fetch(PDO::FETCH_ASSOC);
        return $k['n'] > 0 && (int) $k['a'] === (int) $k['n'];
    }

    /** Bestand des Artikels und seiner Farben in eigenen Lagern (ohne Messe) */
    private function bestand(int $id): float
    {
        $st = $this->db->prepare("
            SELECT COALESCE(SUM(lb.bestand), 0) FROM lagerbestand lb
            JOIN lager l ON l.id = lb.lager_id AND l.typ <> 'messe'
            WHERE lb.artikel_id = ? OR lb.artikel_id IN (SELECT id FROM artikel WHERE vaterartikel_id = ?)");
        $st->execute([$id, $id]);
        return (float) $st->fetchColumn();
    }

    /** "10 x 10 cm = 21 M x 28 R" -> "21 M x 28 R / 10 cm" */
    private function maschenText(string $w): string
    {
        return preg_match('/=\s*(.+)$/u', $w, $t) ? trim($t[1]) . ' / 10 cm' : $w;
    }

    /** Erstes Bild des Artikels (sonst einer Farbe), als JPEG-Base64 */
    private function bild(int $id): string
    {
        $st = $this->db->prepare("
            SELECT b.artikel_id, b.dateiname FROM artikel_bilder b
            WHERE b.artikel_id = ? OR b.artikel_id IN (SELECT id FROM artikel WHERE vaterartikel_id = ?)
            ORDER BY b.artikel_id = ? DESC, b.position, b.id LIMIT 1");
        $st->execute([$id, $id, $id]);
        $b = $st->fetch(PDO::FETCH_ASSOC);
        return $b ? (BelegBilder::vorschau((int) $b['artikel_id'], $b['dateiname'], 400) ?? '') : '';
    }

    /** "1 kg = 55,00 €" -- Garn in Gramm immer je kg (AT-Grundpreisverordnung), sonst die gespeicherte Bezugsmenge */
    private function grundpreisText(array $a, int $id, float $preis): string
    {
        // Vater ohne Füllmenge: von der ersten Farbe
        if ((float) $a['inhalt_menge'] <= 0) {
            $st = $this->db->prepare("SELECT inhalt_menge, inhalt_einheit, grundpreis_bezugsmenge, grundpreis_anzeigen
                                      FROM artikel WHERE vaterartikel_id = ? AND inhalt_menge > 0 LIMIT 1");
            $st->execute([$id]);
            if ($k = $st->fetch(PDO::FETCH_ASSOC)) $a = array_merge($a, $k);
        }
        $menge = (float) $a['inhalt_menge'];
        $einheit = (string) $a['inhalt_einheit'];
        if (!$a['grundpreis_anzeigen'] || $menge <= 0 || $einheit === '') return '';

        if ($a['typ'] === 'GARN' && mb_strtolower($einheit) === 'g') {
            $bezug = 1; $bezugEinheit = 'kg'; $menge = $menge / 1000;
        } else {
            $bezug = (float) $a['grundpreis_bezugsmenge'] ?: 1; $bezugEinheit = $einheit;
        }
        $gp = $preis / $menge * $bezug;
        return rtrim(rtrim(number_format($bezug, 3, ',', ''), '0'), ',') . " $bezugEinheit = " . number_format($gp, 2, ',', '.') . ' €';
    }

    /** "50 g = 125 m" -> "50 g / 125 m Lauflänge" */
    private function lauflaengeText(string $w): string
    {
        if ($w === '') return '';
        return preg_match('/^(.+?)\s*=\s*(.+)$/u', $w, $t) ? "$t[1] / $t[2] Lauflänge" : $w;
    }

    /** ["3", "3,5", "3-3,5"] -> "Nadelstärke 3–3,5" */
    private function nadelText(array $werte): string
    {
        $zahlen = [];
        foreach ($werte as $w) {
            foreach (preg_split('/\s*[-–]\s*/u', $w) as $teil) {
                if (is_numeric($n = str_replace(',', '.', trim($teil)))) $zahlen[] = (float) $n;
            }
        }
        if (!$zahlen) return '';
        $f = fn($n) => str_replace('.', ',', rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'));
        $min = min($zahlen); $max = max($zahlen);
        return 'Nadelstärke ' . ($min === $max ? $f($min) : $f($min) . '–' . $f($max));
    }

    /** QR zur Produktseite im Shop (nur wenn der Artikel dort aktiv und schon übertragen ist) */
    private function qr(int $id, int $shopId): string
    {
        $st = $this->db->prepare("SELECT s.wc_url, x.external_id FROM artikel_shops x JOIN shops s ON s.id = x.shop_id
                                  WHERE x.artikel_id = ? AND x.shop_id = ? AND x.aktiv = 1 AND x.external_id IS NOT NULL LIMIT 1");
        $st->execute([$id, $shopId]);
        $z = $st->fetch(PDO::FETCH_ASSOC);
        if (!$z || !$z['wc_url']) return '';
        $url = rtrim($z['wc_url'], '/') . '/?post_type=product&p=' . (int) $z['external_id'];
        return QrCode::dataUri($url, 240);
    }

    /** Shops, deren Artikel einen QR bekommen können */
    public function shops(): array
    {
        return $this->db->query("SELECT id, name, wc_url FROM shops WHERE ist_aktiv = 1 AND wc_url IS NOT NULL AND wc_url <> '' ORDER BY id")
                        ->fetchAll(PDO::FETCH_ASSOC);
    }
}
