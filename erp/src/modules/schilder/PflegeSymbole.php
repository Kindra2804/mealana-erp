<?php

/**
 * PflegeSymbole – Waschanleitungs-Symbole als kleine SVGs für die Preisschilder (Jacky 2026-11-12).
 *
 * Schlüssel = merkmal_werte.symbol des Merkmals "Pflege" (Migration 233). Gezeichnet nach den
 * Grundformen der ISO 3758: Bottich (waschen), Dreieck (bleichen), Quadrat mit Kreis (Trockner),
 * Quadrat (trocknen), Bügeleisen, Kreis (chemische Reinigung); Balken darunter = schonend, Kreuz = nicht.
 * Einheitliche viewBox 0 0 30 26, schwarze Linien -- Dompdf rendert sie als <img src="data:image/svg+xml...">.
 */
class PflegeSymbole
{
    private const STRICH = 'fill="none" stroke="#111" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round"';

    /** data:-URI für <img>, null bei unbekanntem Schlüssel */
    public static function dataUri(string $symbol): ?string
    {
        $svg = self::svg($symbol);
        return $svg ? 'data:image/svg+xml;base64,' . base64_encode($svg) : null;
    }

    public static function svg(string $symbol): ?string
    {
        $s = self::STRICH;
        $bottich = "<path d=\"M3 8 L6.5 20 H23.5 L27 8\" $s/><path d=\"M3 8 Q5.5 5 8 8 T13 8 T18 8 T23 8 T27 8\" $s/>";
        $kreuz   = "<path d=\"M3 2 L27 24 M27 2 L3 24\" $s/>";
        $balken  = "<path d=\"M7 24.5 H23\" $s/>";
        $zahl    = fn(string $z) => "<text x=\"15\" y=\"17.5\" font-family=\"DejaVu Sans, Arial\" font-size=\"8\" font-weight=\"bold\" text-anchor=\"middle\" fill=\"#111\">$z</text>";
        $quadrat = "<rect x=\"6\" y=\"2.5\" width=\"18\" height=\"18\" $s/>";
        $eisen   = "<path d=\"M4 19 H26 L24 11 Q22.5 7 15 7 H10 Q6 7 5.5 11 Z\" $s/>";
        $punkt   = fn(float $x) => "<circle cx=\"$x\" cy=\"15\" r=\"1.5\" fill=\"#111\"/>";
        $kreis   = "<circle cx=\"15\" cy=\"12\" r=\"9\" $s/>";
        $p       = "<text x=\"15\" y=\"16\" font-family=\"DejaVu Sans, Arial\" font-size=\"11\" font-weight=\"bold\" text-anchor=\"middle\" fill=\"#111\">P</text>";

        $inhalt = match ($symbol) {
            'handwaesche'      => $bottich . "<path d=\"M11 18 V13 M13.5 18 V11.5 M16 18 V12 M18.5 18 V13.5 M11 16 Q11 19.5 14.5 19.5 H17 Q19.5 19.5 19.5 16.5\" " . str_replace('1.7', '1.3', $s) . "/>",
            'wasch30'          => $bottich . $zahl('30'),
            'wasch30_schon'    => $bottich . $zahl('30') . $balken,
            'wasch40'          => $bottich . $zahl('40'),
            'nicht_waschen'    => $bottich . $kreuz,
            'nicht_bleichen'   => "<path d=\"M15 3 L27 22 H3 Z\" $s/>" . $kreuz,
            'kein_trockner'    => $quadrat . "<circle cx=\"15\" cy=\"11.5\" r=\"6.5\" $s/>" . $kreuz,
            'trockner_niedrig' => $quadrat . "<circle cx=\"15\" cy=\"11.5\" r=\"6.5\" $s/>" . "<circle cx=\"15\" cy=\"11.5\" r=\"1.5\" fill=\"#111\"/>",
            'liegend'          => $quadrat . "<path d=\"M9.5 11.5 H20.5\" $s/>",
            'buegeln1'         => $eisen . $punkt(15),
            'buegeln2'         => $eisen . $punkt(12.5) . $punkt(17.5),
            'nicht_buegeln'    => $eisen . $kreuz,
            'reinigung_p'      => $kreis . $p,
            'reinigung_p_schon'=> $kreis . $p . $balken,
            'nicht_reinigen'   => $kreis . $kreuz,
            default            => null,
        };
        if ($inhalt === null) return null;
        return '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="26" viewBox="0 0 30 26">' . $inhalt . '</svg>';
    }
}
