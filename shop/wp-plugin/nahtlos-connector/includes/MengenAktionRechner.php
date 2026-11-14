<?php

/**
 * MengenAktionRechner – Rechenkern der Mengen-Aktionen, OHNE Datenbank.
 *
 * Gleiche Rechnung an drei Stellen – bei Änderungen ALLE nachziehen:
 *   - PHP (diese Datei): ERP, Woo-Plugin (Kopie im NAHTLOS Connector)
 *   - JS:  public/js/mengen_aktion_rechner.js (Kasse)
 *   - Prüffälle für beide: tests/mengen_aktion_faelle.json (php tests/mengen_aktion_test.php, node tests/mengen_aktion_test.js)
 *
 * Regeln (Jacky 2026-11-14):
 *   - Aktion wiederholt sich: 9 Stück bei "4 für 12" = 2 Pakete + 1 Stück normal
 *   - x_fuer_y:      die TEUERSTEN Stücke zuerst ins Paket; je Paket zahlt man paketpreis
 *   - kauf_x_zahl_y: Stücke absteigend nach Preis in Pakete zu X; je Paket sind die (X−Y) günstigsten gratis
 *   - paketpreise:   mehrere Paketgrößen (z.B. 2 für 4,50 · 4 für 8 · 8 für 15 · 12 für 20); die Menge wird so aus
 *                    Paketen zusammengesetzt, dass der Gesamtpreis am günstigsten ist, der Rest einzeln zum Normalpreis
 *                    (teuerste Stücke in die Pakete). Raunzmarker: 5 Stk = 8,00 + 2,50 = 10,50 („schöne“ Preise)
 *   - Der Rabatt wird GLEICHMÄSSIG (gleicher Prozentsatz) auf alle Stücke in Paketen verteilt
 *   - Ist ein "Paket" nicht billiger als normal, gibt es keinen Rabatt
 *   - Ein Artikel nimmt nur an EINER Aktion teil (die erste in der Liste, in der er vorkommt)
 *   - Reduzierte Artikel (Sale/Aktion) und Nicht-Endkunden filtert der Aufrufer vorher heraus (ist_reduziert)
 *   - Gerechnet wird in ganzen Cent; die Summe der Positionen ist exakt
 *
 * Eingabe:
 *   $aktionen   [['id', 'art', 'menge', 'zahl_menge', 'paketpreis', 'badge_text', 'artikel_ids' => int[],
 *                 'pakete' => [['menge', 'preis'], ...] (nur paketpreise; 'menge' = kleinste Paketgröße)], ...]
 *   $positionen [['key' => beliebig, 'artikel_id', 'menge' (ganze Stück), 'preis' (Einzelpreis brutto), 'ist_reduziert' => bool], ...]
 * Ausgabe:
 *   ['positionen' => [key => ['aktion_id', 'badge_text', 'stueck_im_paket', 'gesamt_alt', 'gesamt_neu', 'einzelpreis_neu', 'rabatt_prozent']],
 *    'hinweise'   => [['aktion_id', 'badge_text', 'pakete', 'stueck', 'fehlen', 'ersparnis'], ...]]
 *   Positionen ohne Rabatt fehlen in 'positionen'. 'fehlen' = Stück bis zum nächsten Paket (für den Warenkorb-Hinweis).
 */
class MengenAktionRechner
{
    public static function berechne(array $aktionen, array $positionen): array
    {
        $erg = ['positionen' => [], 'hinweise' => []];
        $vergeben = [];   // key => true: Position nimmt schon an einer Aktion teil

        foreach ($aktionen as $a) {
            $x = (int) $a['menge'];
            if ($x < 2) continue;
            $inAktion = array_flip(array_map('intval', $a['artikel_ids'] ?? []));

            // Stücke der Aktion einzeln, in Cent
            $stuecke = [];
            foreach ($positionen as $p) {
                $key = $p['key'];
                if (isset($vergeben[$key]) || !empty($p['ist_reduziert']) || !isset($inAktion[(int) $p['artikel_id']])) continue;
                $cent = (int) round((float) $p['preis'] * 100);
                $menge = (int) $p['menge'];
                if ($cent <= 0 || $menge <= 0) continue;
                $vergeben[$key] = true;
                for ($i = 0; $i < $menge; $i++) $stuecke[] = ['key' => $key, 'cent' => $cent];
            }
            if (!$stuecke) continue;

            // teuerste zuerst; bei gleichem Preis stabile Reihenfolge (Position, dann Stück)
            $sortiert = [];
            foreach ($stuecke as $i => $s) $sortiert[] = $s + ['i' => $i];
            usort($sortiert, fn($u, $v) => $v['cent'] <=> $u['cent'] ?: $u['i'] <=> $v['i']);

            $n = count($sortiert);
            if ($a['art'] === 'paketpreise') {
                [$imPaketAnzahl, $paketZiel, $pakete] = self::paketPlan($a['pakete'] ?? [], array_column($sortiert, 'cent'));
                $fehlen = self::paketFehlen($a['pakete'] ?? [], $n);
            } else {
                $pakete = intdiv($n, $x);
                $imPaketAnzahl = $pakete * $x;
                $fehlen = $x - $n % $x;
            }
            $hinweis = ['aktion_id' => (int) $a['id'], 'badge_text' => $a['badge_text'], 'pakete' => $pakete,
                        'stueck' => $n, 'fehlen' => $fehlen, 'ersparnis' => 0.0];

            if ($pakete > 0) {
                $imPaket = array_slice($sortiert, 0, $imPaketAnzahl);
                $normal  = array_sum(array_column($imPaket, 'cent'));

                if ($a['art'] === 'paketpreise') {
                    $ziel = $paketZiel;
                } elseif ($a['art'] === 'x_fuer_y') {
                    $ziel = $pakete * (int) round((float) $a['paketpreis'] * 100);
                } else {   // kauf_x_zahl_y: je Paket die (X−Y) günstigsten gratis
                    $gratis = $x - (int) $a['zahl_menge'];
                    $ziel = $normal;
                    for ($k = 0; $k < $pakete; $k++) {
                        $paket = array_slice($imPaket, $k * $x, $x);   // absteigend sortiert → die letzten sind die günstigsten
                        for ($g = 0; $g < $gratis; $g++) $ziel -= $paket[$x - 1 - $g]['cent'];
                    }
                }

                if ($ziel < $normal) {
                    // gleicher Prozentsatz für alle Stücke im Paket, je Position zusammengefasst, Cent-Rest an die größte Position
                    $jePos = [];
                    foreach ($imPaket as $s) {
                        $jePos[$s['key']]['stueck'] = ($jePos[$s['key']]['stueck'] ?? 0) + 1;
                        $jePos[$s['key']]['alt']    = ($jePos[$s['key']]['alt'] ?? 0) + $s['cent'];
                    }
                    $verteilt = 0;
                    foreach ($jePos as $key => &$jp) {
                        $jp['neu'] = (int) round($jp['alt'] * $ziel / $normal);
                        $verteilt += $jp['neu'];
                    }
                    unset($jp);
                    if ($verteilt !== $ziel) {
                        uasort($jePos, fn($u, $v) => $v['alt'] <=> $u['alt']);
                        $groesste = array_key_first($jePos);
                        $jePos[$groesste]['neu'] += $ziel - $verteilt;
                    }

                    foreach ($positionen as $p) {
                        $key = $p['key'];
                        if (!isset($jePos[$key])) continue;
                        $altGesamt = (int) round((float) $p['preis'] * 100) * (int) $p['menge'];
                        $neuGesamt = $altGesamt - $jePos[$key]['alt'] + $jePos[$key]['neu'];
                        $erg['positionen'][$key] = [
                            'aktion_id'       => (int) $a['id'],
                            'badge_text'      => $a['badge_text'],
                            'stueck_im_paket' => $jePos[$key]['stueck'],
                            'gesamt_alt'      => $altGesamt / 100,
                            'gesamt_neu'      => $neuGesamt / 100,
                            'einzelpreis_neu' => round($neuGesamt / 100 / (int) $p['menge'], 4),
                            'rabatt_prozent'  => round((1 - $neuGesamt / $altGesamt) * 100, 2),
                        ];
                    }
                    $hinweis['ersparnis'] = ($normal - $ziel) / 100;
                }
            }
            $erg['hinweise'][] = $hinweis;
        }
        return $erg;
    }

    /**
     * paketpreise: welche Stücke kommen in Pakete, was kosten sie, wie viele Pakete?
     * $cents = Stückpreise absteigend. Die k teuersten gehen in Pakete (k exakt aus Paketgrößen füllbar),
     * die übrigen (günstigsten) zahlen Normalpreis. Gewählt wird das k mit dem kleinsten Gesamtpreis
     * (bei Gleichstand das kleinere k = weniger Pakete).
     * @return array [k, Paketpreis in Cent, Anzahl Pakete]
     */
    private static function paketPlan(array $pakete, array $cents): array
    {
        $n = count($cents);
        $groessen = [];
        foreach ($pakete as $p) {
            $m = (int) $p['menge'];
            if ($m >= 2) $groessen[$m] = (int) round((float) $p['preis'] * 100);
        }
        // best[m] = [Cent, Pakete] für genau m Stück aus Paketen; null = nicht füllbar
        $best = [0 => [0, 0]];
        for ($m = 1; $m <= $n; $m++) {
            $best[$m] = null;
            foreach ($groessen as $g => $preis) {
                if ($g > $m || $best[$m - $g] === null) continue;
                $kand = [$best[$m - $g][0] + $preis, $best[$m - $g][1] + 1];
                if ($best[$m] === null || $kand[0] < $best[$m][0]) $best[$m] = $kand;
            }
        }
        $rest = array_sum($cents);   // Summe der Stücke ab Index k (zu Beginn: alle)
        $wahl = [0, 0, 0];
        $bestGesamt = $rest;
        for ($k = 1; $k <= $n; $k++) {
            $rest -= $cents[$k - 1];
            if ($best[$k] === null) continue;
            if ($best[$k][0] + $rest < $bestGesamt) {
                $bestGesamt = $best[$k][0] + $rest;
                $wahl = [$k, $best[$k][0], $best[$k][1]];
            }
        }
        return $wahl;
    }

    /** paketpreise: Stück bis zur nächsten Paketgröße (über den Rest nach den größten Paketen) */
    private static function paketFehlen(array $pakete, int $n): int
    {
        $g = array_map(fn($p) => (int) $p['menge'], $pakete);
        sort($g);
        if (!$g) return 0;
        $r = $n % end($g);
        foreach ($g as $m) if ($m > $r) return $m - $r;
        return 0;
    }
}
