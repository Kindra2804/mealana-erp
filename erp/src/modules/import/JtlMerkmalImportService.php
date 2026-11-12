<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/JtlCsvReader.php';
require_once __DIR__ . '/../merkmale/MerkmalService.php';

/**
 * JtlMerkmalImportService – übernimmt die Artikelmerkmale aus dem JTL-Ameise-Export
 * "Artikelmerkmale" (Spalten Artikelnummer / Merkmal / Merkmalwert) in unsere Merkmale (Migration 233).
 *
 * Zuordnung JTL-Merkmal -> slug:
 *   Wollqualitäten:          -> wollqualitaet (mehrfach); "GOTS Zertifiziert"/"BIO-Zertifiziert" -> auszeichnung GOTS/BIO
 *   Zusammensetzung:         -> zusammensetzung
 *   Gewicht/Länge:           -> gewicht_laenge (+ Lauflänge je 100 g wird abgeleitet)
 *   Maschenprobe:            -> maschenprobe (+ Maschenprobe/10 cm wird abgeleitet)
 *   Garngruppe:              -> garngruppe (über den Buchstaben A-F auf die festen Werte)
 *   Empfohlene Nadelstärke:  -> nadelstaerke (mehrfach)
 *   Pflegeanleitung:         -> ignoriert (enthält Garnnamen, keine Pflegeangaben)
 *
 * - Ersetzt je Artikel nur die Merkmale, die in der Datei vorkommen (Pflege/Handgefärbt bleiben).
 * - Väter ohne eigene Zeilen bekommen den häufigsten Wert ihrer Kinder.
 * - Schreibweisen werden vereinheitlicht (Leerzeichen, "3 - 3,5" -> "3-3,5", "50g = 390m" -> "50 g = 390 m").
 * - Wiederholbar: ein zweiter Lauf ergibt denselben Stand.
 */
class JtlMerkmalImportService
{
    private const ZUORDNUNG = [
        'wollqualitäten'         => 'wollqualitaet',
        'zusammensetzung'        => 'zusammensetzung',
        'gewicht/länge'          => 'gewicht_laenge',
        'maschenprobe'           => 'maschenprobe',
        'garngruppe'             => 'garngruppe',
        'empfohlene nadelstärke' => 'nadelstaerke',
    ];
    private const IGNORIERT = ['pflegeanleitung'];
    private const EINZELWERT = ['zusammensetzung', 'gewicht_laenge', 'maschenprobe', 'garngruppe'];
    private const AUSZEICHNUNG = ['gots zertifiziert' => 'GOTS', 'bio-zertifiziert' => 'BIO'];

    private PDO $db;
    private MerkmalService $merkmale;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->merkmale = new MerkmalService();
    }

    /**
     * @return array Bericht: zahlen, nicht_gefunden[], uneinheitlich[], mehrfach[], unbekannt[]
     */
    public function importiere(string $pfad, bool $probelauf): array
    {
        foreach (array_merge(array_values(self::ZUORDNUNG), ['auszeichnung', 'lauflaenge_bereich', 'maschenprobe_10cm']) as $slug) {
            if (!$this->merkmale->merkmalId($slug)) throw new RuntimeException("Merkmal '$slug' fehlt -- zuerst Migration 233 einspielen.");
        }

        $bericht = ['zahlen' => ['zeilen' => 0, 'artikel_datei' => 0, 'artikel_gefunden' => 0, 'vaeter_von_kindern' => 0,
                                 'ignoriert' => 0, 'werte_neu' => 0],
                    'nicht_gefunden' => [], 'uneinheitlich' => [], 'mehrfach' => [], 'unbekannt' => []];

        // 1) Datei lesen: artikelnummer => slug => [werte]
        $daten = [];
        foreach (JtlCsvReader::lese($pfad) as $z) {
            $bericht['zahlen']['zeilen']++;
            $nr  = trim($z['Artikelnummer'] ?? '');
            $mk  = mb_strtolower(rtrim(trim($z['Merkmal'] ?? ''), ':'));
            $wert = $this->normalisiereAllgemein($z['Merkmalwert'] ?? '');
            if ($nr === '' || $mk === '' || $wert === '') continue;

            if (in_array($mk, self::IGNORIERT, true)) { $bericht['zahlen']['ignoriert']++; continue; }
            $slug = self::ZUORDNUNG[$mk] ?? null;
            if (!$slug) { $bericht['unbekannt'][$z['Merkmal']] = ($bericht['unbekannt'][$z['Merkmal']] ?? 0) + 1; continue; }

            if ($slug === 'wollqualitaet' && isset(self::AUSZEICHNUNG[mb_strtolower($wert)])) {
                $daten[$nr]['auszeichnung'][] = self::AUSZEICHNUNG[mb_strtolower($wert)];
                continue;
            }
            $daten[$nr][$slug][] = $this->normalisiere($slug, $wert);
        }
        $bericht['zahlen']['artikel_datei'] = count($daten);

        // 2) Artikelnummern auflösen
        $artikel = [];
        foreach ($this->db->query("SELECT id, artikelnummer, vaterartikel_id, ist_vater FROM artikel") as $a) {
            $artikel[$a['artikelnummer']] = $a;
        }

        $werteVorher = (int) $this->db->query("SELECT COUNT(*) FROM merkmal_werte")->fetchColumn();
        $this->db->beginTransaction();
        try {
            $beruehrt = [];
            $kinderJeVater = [];   // vater_id => [kind_id => slug => werte]
            foreach ($daten as $nr => $slugs) {
                $a = $artikel[$nr] ?? null;
                if (!$a) { $bericht['nicht_gefunden'][] = $nr; continue; }
                $bericht['zahlen']['artikel_gefunden']++;
                $id = (int) $a['id'];
                foreach ($slugs as $slug => $werte) {
                    $werte = array_values(array_unique($werte));
                    if ($slug === 'auszeichnung') {
                        foreach ($werte as $w) $this->merkmale->wertHinzufuegen($id, 'auszeichnung', $w);
                        continue;
                    }
                    if (in_array($slug, self::EINZELWERT, true) && count($werte) > 1) {
                        $bericht['mehrfach'][] = "$nr  $slug: " . implode(' | ', $werte) . '  -> erster genommen';
                        $werte = [$werte[0]];
                    }
                    $this->setze($id, $slug, $werte);
                    if ($a['vaterartikel_id']) $kinderJeVater[(int) $a['vaterartikel_id']][$id][$slug] = $werte;
                }
                $beruehrt[$id] = true;
            }

            // 3) Väter: fehlende Merkmale vom häufigsten Kind-Wert; Abweichungen melden
            $nrJeId = array_column($artikel, 'artikelnummer', 'id');
            $hatEigene = [];
            foreach ($daten as $nr => $slugs) if (isset($artikel[$nr])) $hatEigene[(int) $artikel[$nr]['id']] = array_keys($slugs);
            foreach ($kinderJeVater as $vaterId => $kinder) {
                $slugsDerKinder = [];
                foreach ($kinder as $k) foreach ($k as $slug => $w) $slugsDerKinder[$slug][] = implode("\x1F", $w);
                foreach ($slugsDerKinder as $slug => $varianten) {
                    $haeufig = array_count_values($varianten);
                    arsort($haeufig);
                    $mehrheit = (string) array_key_first($haeufig);
                    if (count($haeufig) > 1) {
                        $bericht['uneinheitlich'][] = ($nrJeId[$vaterId] ?? $vaterId) . "  $slug: "
                            . implode(' | ', array_map(fn($v, $n) => str_replace("\x1F", ' / ', $v) . " ({$n}×)", array_keys($haeufig), $haeufig));
                    }
                    if (!in_array($slug, $hatEigene[$vaterId] ?? [], true)) {
                        $this->setze($vaterId, $slug, explode("\x1F", $mehrheit));
                        $beruehrt[$vaterId] = true;
                        $bericht['zahlen']['vaeter_von_kindern']++;
                    }
                }
            }

            foreach (array_keys($beruehrt) as $id) $this->merkmale->bereicheAbleiten($id);

            $bericht['zahlen']['werte_neu'] = (int) $this->db->query("SELECT COUNT(*) FROM merkmal_werte")->fetchColumn() - $werteVorher;
            $probelauf ? $this->db->rollBack() : $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $bericht;
    }

    private function setze(int $artikelId, string $slug, array $werte): void
    {
        if ($slug === 'garngruppe') {
            $werte = array_filter(array_map(fn($w) => $this->garngruppeWert($w), $werte));
        }
        $this->merkmale->setzeMerkmal($artikelId, $slug, $werte);
    }

    /** "B - von 22 bis 20 Maschen" -> Text des festen Werts mit Schlüssel gg_b */
    private function garngruppeWert(string $w): ?string
    {
        $symbol = preg_match('/^([A-F])\b/u', $w, $t) ? 'gg_' . strtolower($t[1]) : 'gg_andere';
        static $cache = [];
        if (!array_key_exists($symbol, $cache)) {
            $st = $this->db->prepare("SELECT w.wert FROM merkmal_werte w JOIN merkmale m ON m.id = w.merkmal_id
                                      WHERE m.slug = 'garngruppe' AND w.symbol = ? LIMIT 1");
            $st->execute([$symbol]);
            $cache[$symbol] = $st->fetchColumn() ?: null;
        }
        return $cache[$symbol];
    }

    private function normalisiereAllgemein(string $w): string
    {
        $w = str_replace(['’', '´', "\u{00A0}"], ['', '', ' '], $w);
        return trim(preg_replace('/\s+/u', ' ', $w));
    }

    private function normalisiere(string $slug, string $w): string
    {
        switch ($slug) {
            case 'nadelstaerke':     // "3 - 3,5", "1,25 – 1,5", "6-8" -> "3-3,5"
                return preg_replace('/\s*[-–]\s*/u', '-', $w);
            case 'gewicht_laenge':   // "50g = 390m" -> "50 g = 390 m"
                return preg_replace('/^([\d.,]+)\s*g\s*=\s*([\d.,]+)\s*m$/iu', '$1 g = $2 m', $w);
            case 'zusammensetzung':  // "77% Mohair 23% Seide" bleibt, nur Abstände vereinheitlichen
                $w = preg_replace('/(\d)\s+%/u', '$1%', $w);
                $w = preg_replace('/\s*,\s*/u', ', ', $w);
                return preg_replace('/(\d+%)(?=\p{L})/u', '$1 ', $w);
            case 'maschenprobe':     // "= 09 M" -> "= 9 M"
                return preg_replace('/(?<=\s)0(\d)/u', '$1', $w);
        }
        return $w;
    }
}
