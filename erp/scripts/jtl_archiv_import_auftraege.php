<?php
/**
 * Importiert Aufträge aus einem JTL-Auftrags-Export als Archiv-Aufträge
 * (kanal='jtl_archiv'). Setzt voraus, dass die zugehörigen Kunden bereits per
 * jtl_archiv_import_kunden.php importiert wurden.
 * Wiederholbar: ein erneuter Lauf (auch mit einem größeren Export) überspringt
 * bereits importierte Aufträge (Dedup über auftrag_nr = JTL-Auftragsnummer).
 *
 * Aufruf:
 *   php scripts/jtl_archiv_import_auftraege.php <csv-pfad> [--dry-run]
 *
 * Große Exporte (Voll-Export seit Jahren) brauchen mehr als das Standard-Limit,
 * JtlCsvReader haelt die komplette Datei als Array im Speicher.
 */

ini_set('memory_limit', '4096M');

require_once __DIR__ . '/../src/modules/import/JtlArchivImportService.php';

$csvPfad = $argv[1] ?? null;
$dryRun  = in_array('--dry-run', $argv, true);

if (!$csvPfad || !is_file($csvPfad)) {
    fwrite(STDERR, "Nutzung: php scripts/jtl_archiv_import_auftraege.php <csv-pfad> [--dry-run]\n");
    exit(1);
}

echo ($dryRun ? "[DRY RUN] " : '') . "Importiere Aufträge aus $csvPfad ...\n";

$service  = new JtlArchivImportService();
$ergebnis = $service->importiereAuftraege($csvPfad, $dryRun);

echo "\n";
echo ($dryRun ? '[DRY RUN] ' : '') . "{$ergebnis['neu']} Aufträge " . ($dryRun ? 'würden neu angelegt' : 'neu angelegt') . "\n";
echo "{$ergebnis['uebersprungen']} bereits vorhanden (auftrag_nr), übersprungen\n";

if (!empty($ergebnis['fehler'])) {
    echo count($ergebnis['fehler']) . " Fehler:\n";
    foreach ($ergebnis['fehler'] as $f) {
        echo "  - $f\n";
    }
    exit(1);
}

echo "Fertig.\n";
