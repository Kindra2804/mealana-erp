<?php

/**
 * JtlCsvReader – gemeinsame Einlese-Logik für JTL-Wawi-CSV-Exporte
 *
 * JTL exportiert Windows-1252-kodiert (deutsche Windows-Excel-Konvention), teils mit
 * UTF-8-BOM, mit Semikolon oder Tab als Trennzeichen und einer trailing leeren Spalte
 * durch das Endsemikolon in der Kopfzeile. Wird von JtlVaterKindImportService UND
 * JtlBilderImportService genutzt — beide brauchen exakt dieselbe Robustheit.
 */
class JtlCsvReader
{
    /** Liest eine JTL-CSV-Datei ein und gibt die Datenzeilen als Spaltenname => Wert zurück. */
    public static function lese(string $pfad): array
    {
        $rohdaten = file_get_contents($pfad);
        if (str_starts_with($rohdaten, "\xEF\xBB\xBF")) {
            $rohdaten = substr($rohdaten, 3);
        }
        if (!mb_check_encoding($rohdaten, 'UTF-8')) {
            $rohdaten = mb_convert_encoding($rohdaten, 'UTF-8', 'Windows-1252');
        }
        $rohdaten = str_replace(["\r\n", "\r"], "\n", $rohdaten);

        $ersteLinie = strtok($rohdaten, "\n");
        $delimiter  = str_contains($ersteLinie, "\t") ? "\t" : ';';

        $tmp = tmpfile();
        fwrite($tmp, $rohdaten);
        rewind($tmp);

        $kopfzeile = fgetcsv($tmp, 0, $delimiter);
        if (!$kopfzeile) {
            fclose($tmp);
            return [];
        }
        $kopfzeile = array_map('trim', $kopfzeile);

        $zeilen = [];
        while (($zeile = fgetcsv($tmp, 0, $delimiter)) !== false) {
            if (count(array_filter($zeile, fn($v) => $v !== '')) === 0) continue;
            $row = [];
            foreach ($kopfzeile as $i => $spalte) {
                if ($spalte === '') continue; // trailing leere Spalte durch Endsemikolon im JTL-Export
                $row[$spalte] = isset($zeile[$i]) ? trim($zeile[$i]) : '';
            }
            $zeilen[] = $row;
        }
        fclose($tmp);
        return $zeilen;
    }
}
