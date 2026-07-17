<?php

/**
 * DatevFormatter – wandelt Buchungszeilen (aus BuchhaltungExportService) in
 * eine generische CSV bzw. eine DATEV-EXTF-Buchungsstapel-Datei.
 *
 * DATEV-Format: öffentlich dokumentierte "Buchungsstapel"-Schnittstelle (Format-
 * Kennzeichen 21). Enthält nur den gängigen Kern-Spaltensatz (Umsatz, Soll/Haben,
 * Konto, Gegenkonto, Belegdatum, Belegfeld 1, Buchungstext, WKZ) — reicht für den
 * Standard-Import, aber vor dem ERSTEN echten Import unbedingt mit dem Steuerberater
 * anhand einer Testdatei prüfen (DATEV-Programmversionen unterscheiden sich in
 * optionalen Spalten).
 */
class DatevFormatter
{
    public static function alsCsv(array $buchungen): string
    {
        $zeilen = ["Datum;Beleg;Konto;Gegenkonto;Betrag;Soll_Haben;Steuersatz;Buchungstext"];
        foreach ($buchungen as $b) {
            $zeilen[] = implode(';', [
                date('d.m.Y', strtotime($b['datum'])),
                self::csvFeld($b['belegnr']),
                $b['konto'],
                $b['gegenkonto'],
                number_format($b['betrag'], 2, ',', ''),
                $b['soll_haben'],
                $b['satz'] !== null ? number_format((float)$b['satz'], 2, ',', '') : '',
                self::csvFeld($b['text']),
            ]);
        }
        return "\xEF\xBB\xBF" . implode("\r\n", $zeilen) . "\r\n"; // UTF-8 BOM für Excel
    }

    private static function csvFeld(string $wert): string
    {
        return str_contains($wert, ';') || str_contains($wert, '"')
            ? '"' . str_replace('"', '""', $wert) . '"'
            : $wert;
    }

    /**
     * @param array{datev_berater_nr:string, datev_mandant_nr:string, datev_wj_beginn:string, datev_sachkontenlaenge:string} $einstellungen
     */
    public static function alsDatev(array $buchungen, array $einstellungen, string $von, string $bis, string $firmenname): string
    {
        $jetzt      = date('YmdHis000');
        $wjBeginn   = !empty($einstellungen['datev_wj_beginn']) ? date('Ymd', strtotime($einstellungen['datev_wj_beginn'])) : date('Y0101');
        $sachlaenge = (int)($einstellungen['datev_sachkontenlaenge'] ?: 4);
        $beraterNr  = preg_replace('/\D/', '', $einstellungen['datev_berater_nr'] ?? '') ?: '0';
        $mandantNr  = preg_replace('/\D/', '', $einstellungen['datev_mandant_nr'] ?? '') ?: '0';

        $kopf = [
            'EXTF', 700, 21, 'Buchungsstapel', 13, $jetzt, '', '', 'MeaLana-ERP', '',
            $beraterNr, $mandantNr, $wjBeginn, $sachlaenge,
            date('Ymd', strtotime($von)), date('Ymd', strtotime($bis)),
            'Umsätze ' . date('d.m.Y', strtotime($von)) . '-' . date('d.m.Y', strtotime($bis)),
            '', 1, '', 0, 'EUR', '', '', '', '', '',
        ];
        $kopfZeile = implode(';', array_map([self::class, 'datevFeld'], $kopf));

        $spalten = [
            'Umsatz (ohne Soll/Haben-Kz)', 'Soll/Haben-Kennzeichen', 'WKZ Umsatz', 'Kurs',
            'Basis-Umsatz', 'WKZ Basis-Umsatz', 'Konto', 'Gegenkonto (ohne BU-Schlüssel)',
            'BU-Schlüssel', 'Belegdatum', 'Belegfeld 1', 'Belegfeld 2', 'Skonto', 'Buchungstext',
        ];
        $spaltenZeile = implode(';', array_map([self::class, 'datevFeld'], $spalten));

        $zeilen = [$kopfZeile, $spaltenZeile];
        foreach ($buchungen as $b) {
            $zeilen[] = implode(';', array_map([self::class, 'datevFeld'], [
                number_format($b['betrag'], 2, ',', ''),
                $b['soll_haben'],
                'EUR', '', '', '',
                $b['konto'],
                $b['gegenkonto'],
                '', // BU-Schlüssel bewusst leer -> Steuer wird als eigene Buchungszeile gebucht, keine DATEV-Steuerautomatik
                date('dm', strtotime($b['datum'])), // Belegdatum im Buchungsstapel nur TTMM (Jahr kommt aus dem Kopfsatz)
                substr($b['belegnr'], 0, 36),
                '', '',
                mb_substr($b['text'], 0, 60),
            ]));
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $zeilen) . "\r\n";
    }

    private static function datevFeld($wert): string
    {
        if (is_int($wert) || is_float($wert)) return (string)$wert;
        $wert = (string)$wert;
        return '"' . str_replace('"', '""', $wert) . '"';
    }
}
