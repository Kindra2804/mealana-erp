<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/QrCode.php';
require_once __DIR__ . '/../dokumente/PdfGenerator.php';
require_once __DIR__ . '/../preise/PreisService.php';
require_once __DIR__ . '/../preise/PreisRepository.php';

/**
 * PreisschildService – Preisschilder aus Artikeldaten (Jacky + Babsi 2026-11-12, Entwurf D:\ERP\preisschilder_mockup.svg).
 *
 * Vorlage "messe": 8 Schilder je A4 (2 x 4, je 95 x 68 mm, Rand für den Drucker), eines je Garnqualität:
 *   Name, Hersteller, Zusammensetzung, Gewicht/Lauflänge, Nadelstärke (kleinste–größte), Preis,
 *   Grundpreis (Garn immer je kg, wie im Shop), optional "statt"-Preis (Aktion / Messepreis) und QR zum Shop.
 *
 * - Kind-Artikel werden zu ihrem Vater zusammengefasst (ein Schild je Qualität, nicht je Farbe).
 * - Preis = Effektivpreis der Standard-Kundengruppe (Aktion/SALE zählt). Messepreis: x % auf den
 *   Standardpreis, sofern das günstiger ist als ein laufender Aktionspreis.
 * - Haben die Farben verschiedene Preise, steht "ab" vor dem Preis.
 * - Jeder Druck wird in preisschild_drucke protokolliert (für "Schilder veraltet").
 *
 * Merkmale werden über ihren slug gelesen (Migration 233).
 */
class PreisschildService
{
    public const VORLAGEN = ['messe' => ['name' => 'Messe-Schild', 'je_seite' => 8]];

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
            $d = $this->daten($id, []);
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
                   a.grundpreis_anzeigen, t.code AS typ, h.name AS hersteller
            FROM artikel a
            JOIN artikel_typen t ON t.id = a.artikeltyp_id
            LEFT JOIN hersteller h ON h.id = a.hersteller_id
            WHERE a.id = ?");
        $st->execute([$id]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a) return null;

        $m = $this->merkmale($id);
        [$preis, $statt, $ab] = $this->preis($id, (float) ($opt['messe_prozent'] ?? 0));

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
            $preise[(int) $k] = ['eff' => (float) $eff['brutto_vk'], 'std' => $std ? (float) $std['brutto_vk'] : (float) $eff['brutto_vk']];
        }
        if (!$preise) return [null, null, false];

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
        return [round($preis, 2), $statt, $ab];
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
