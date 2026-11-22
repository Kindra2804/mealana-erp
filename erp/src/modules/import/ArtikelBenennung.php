<?php
/**
 * ArtikelBenennung – neues Nummern- und Namensschema (Babsi/Jacky 2026-11-21/22)
 *
 *   Artikelnummer  Kürzel-Vater-Kind, Kind 4-stellig      LN-113-0001
 *   Farb-Kind      "Vater Farbe [Art] (0001)"             Karisma natur [Uni] (0001)
 *   ohne Farbname  "Vater [Art] (0001)"                   Hannah [Uni] (0001)
 *   Nadeln/Größen  Nummer bleibt (= Herstellernummer),    KP-20415  "Symfonie Nadelspitzen 3.00 mm"
 *                  Name "Vater Länge Stärke"
 *
 * - Kürzel kommt vom Hersteller (hersteller.kuerzel, max. 3). Ohne Kürzel bleibt die Nummer, wie sie ist.
 * - Vater-Teil = Vaternummer ohne ihren Buchstaben-Anfang ("LN-113" -> "113", "EL-LE59" -> "LE59").
 * - Kind-Teil  = echte Farbnummer, links mit 0 auf 4 Stellen ("01" -> "0001"); ab 4 Stellen unverändert.
 * - [Art] = Unter-Achse der Farbe ([Uni], [Mix], [Print] …). Wird hier nur GELESEN – an den Unter-Achsen
 *   hängen die Aktionspreise (aktionen_artikel_preise.sub_achse_id), die dürfen nicht kaputtgehen.
 *   Garne ohne Unter-Achse bekommen einfach kein [Art].
 *
 * Reine Rechenlogik ohne DB/Datei – genutzt von der Vorschau (artikel/jtl_benennung.php) und dem JTL-Import.
 */
class ArtikelBenennung
{
    /** Höchstlänge von artikel.artikelnummer */
    public const MAX_NR = 30;

    /** Achsen, die im Namen vor den übrigen stehen ("Vater Länge Stärke") */
    private const ZUERST = '/läng|lang|lauf/i';

    /** Vater-Teil der Nummer: Buchstaben-Anfang + Trennstrich weg; reine Buchstaben-Nummern bleiben ganz. */
    public static function vaterTeil(string $nr): string
    {
        $nr  = trim($nr);
        $rest = preg_replace('/^[A-Za-z]+[-\s]*/', '', $nr);
        return ($rest === '' || $rest === null) ? $nr : $rest;
    }

    /** Farbnummer auf 4 Stellen ("1" -> "0001", "113" -> "0113", "01101" bleibt). */
    public static function vierstellig(string $nr): string
    {
        return strlen($nr) >= 4 ? $nr : str_pad($nr, 4, '0', STR_PAD_LEFT);
    }

    /** Ist das eine Farb-Achse? "Farbe" selbst oder eine Unter-Achse in eckigen Klammern. */
    public static function istFarbAchse(string $achse): bool
    {
        return mb_strtolower($achse) === 'farbe' || str_starts_with($achse, '[');
    }

    /** Neue Nummer für Vater oder Einzelartikel. */
    public static function vaterNummer(string $nr, ?string $kuerzel): string
    {
        return $kuerzel ? $kuerzel . '-' . self::vaterTeil($nr) : $nr;
    }

    /**
     * Rechnet einen Vater samt Kindern um.
     *
     * @param array $vater  ['nr' => (schon umbenannte) Vaternummer, 'name' => Vatername, 'kuerzel' => ?string]
     * @param array $kinder je ['nr' => Kindnummer, 'name' => Rohname, 'achsen' => [['name' => 'Farbe'|'[Uni]'|'Länge', 'wert' => '18 braun'], …]]
     *                      (achsen wie aus JtlVaterKindImportService::parseVariationskombinationen)
     * @return array ['nr', 'hinweise' => [], 'kinder' => [['nr', 'name', 'art', 'typ' => farbe|andere|ohne, 'hinweise' => []], …]]
     */
    public static function rechne(array $vater, array $kinder): array
    {
        $kuerzel  = $vater['kuerzel'] ?: null;
        $vaterNeu = self::vaterNummer($vater['nr'], $kuerzel);
        $vName    = trim($vater['name']);
        $vHinweis = self::nummerHinweise($vaterNeu);
        if (!$kuerzel) $vHinweis[] = 'Hersteller ohne Kürzel – Nummern bleiben';

        $ergebnis = [];
        foreach ($kinder as $k) {
            $farbe  = null;
            $andere = [];
            foreach ($k['achsen'] ?? [] as $a) {
                if ($farbe === null && self::istFarbAchse($a['name'])) $farbe = $a;
                else $andere[] = $a;
            }
            usort($andere, fn($x, $y) => (preg_match(self::ZUERST, $y['name']) <=> preg_match(self::ZUERST, $x['name'])));
            $zusatz = implode(' ', array_map(fn($a) => trim($a['wert']), $andere));

            $hinweise = [];
            $art      = ($farbe && str_starts_with($farbe['name'], '[')) ? $farbe['name'] : null;

            if ($farbe) {
                preg_match('/^\s*(\d+)\s*(.*)$/u', $farbe['wert'], $m);
                $farbNr   = $m[1] ?? null;
                $farbName = trim($m ? $m[2] : $farbe['wert']);
                // Keine Nummer im Farbwert, aber in der Kindnummer (Lang: "LY-71-0324" = perlgrau)
                if ($farbNr === null && preg_match('/-(\d+)$/', $k['nr'], $km)) {
                    $farbNr     = $km[1];
                    $hinweise[] = 'Farbnummer aus Kindnummer';
                }
                $nr4      = $farbNr !== null ? self::vierstellig($farbNr) : null;

                $teile = array_filter([$vName, $farbName, $art, $nr4 !== null ? "($nr4)" : null, $zusatz], fn($t) => $t !== null && $t !== '');
                $name  = implode(' ', $teile);

                if ($nr4 === null) {
                    $nr = $k['nr'];
                    $hinweise[] = 'Farbe ohne Nummer – Nummer bleibt';
                } elseif ($andere) {
                    $nr = $k['nr'];
                    $hinweise[] = 'Farbe + weitere Achse – Nummer bleibt';
                } elseif (!$kuerzel) {
                    $nr = $k['nr'];
                } else {
                    $nr = $vaterNeu . '-' . $nr4;
                }
                $typ = 'farbe';
            } elseif ($andere) {
                $nr   = $k['nr'];
                $name = trim($vName . ' ' . $zusatz);
                $typ  = 'andere';
            } else {
                $nr   = $k['nr'];
                $name = $k['name'] ?? $vName;
                $typ  = 'ohne';
                $hinweise[] = 'keine Achse erkannt – Name aus JTL';
            }

            $ergebnis[] = ['nr' => $nr, 'name' => $name, 'art' => $art, 'typ' => $typ,
                           'hinweise' => array_merge($hinweise, self::nummerHinweise($nr))];
        }

        // Doppelte Kindnummern innerhalb des Vaters (z.B. dieselbe Farbnummer in [Uni] und [Mix])
        $zaehl = array_count_values(array_column($ergebnis, 'nr'));
        foreach ($ergebnis as &$e) {
            if ($zaehl[$e['nr']] > 1) $e['hinweise'][] = 'Nummer doppelt';
        }
        unset($e);

        return ['nr' => $vaterNeu, 'hinweise' => $vHinweis, 'kinder' => $ergebnis];
    }

    /** Hinweise, die jede Nummer betreffen (Länge, Leerzeichen). */
    private static function nummerHinweise(string $nr): array
    {
        $h = [];
        if (strlen($nr) > self::MAX_NR) $h[] = 'Nummer länger als ' . self::MAX_NR . ' Zeichen';
        if (str_contains($nr, ' '))      $h[] = 'Leerzeichen in Nummer';
        return $h;
    }
}
