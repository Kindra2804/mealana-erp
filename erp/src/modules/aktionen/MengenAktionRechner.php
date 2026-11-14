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
 *   - Der Rabatt wird GLEICHMÄSSIG (gleicher Prozentsatz) auf alle Stücke in Paketen verteilt
 *   - Ist ein "Paket" nicht billiger als normal, gibt es keinen Rabatt
 *   - Ein Artikel nimmt nur an EINER Aktion teil (die erste in der Liste, in der er vorkommt)
 *   - Reduzierte Artikel (Sale/Aktion) und Nicht-Endkunden filtert der Aufrufer vorher heraus (ist_reduziert)
 *   - Gerechnet wird in ganzen Cent; die Summe der Positionen ist exakt
 *
 * Eingabe:
 *   $aktionen   [['id', 'art', 'menge', 'zahl_menge', 'paketpreis', 'badge_text', 'artikel_ids' => int[]], ...]
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
            $pakete = intdiv($n, $x);
            $hinweis = ['aktion_id' => (int) $a['id'], 'badge_text' => $a['badge_text'], 'pakete' => $pakete,
                        'stueck' => $n, 'fehlen' => $x - $n % $x, 'ersparnis' => 0.0];

            if ($pakete > 0) {
                $imPaket = array_slice($sortiert, 0, $pakete * $x);
                $normal  = array_sum(array_column($imPaket, 'cent'));

                if ($a['art'] === 'x_fuer_y') {
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
}
