<?php
/**
 * Importiert Kunden aus einem JTL-Kundendaten-Export als Archivdaten.
 * Wiederholbar: ein erneuter Lauf (auch mit einem größeren Export) überspringt
 * bereits importierte Kunden (Dedup über kunden.jtl_kundennummer) und
 * verknüpft nur nach, statt Dubletten anzulegen (E-Mail-Match).
 *
 * Aufruf:
 *   php scripts/jtl_archiv_import_kunden.php <csv-pfad> [--dry-run]
 */

require_once __DIR__ . '/../src/modules/import/JtlArchivImportService.php';

$csvPfad = $argv[1] ?? null;
$dryRun  = in_array('--dry-run', $argv, true);

if (!$csvPfad || !is_file($csvPfad)) {
    fwrite(STDERR, "Nutzung: php scripts/jtl_archiv_import_kunden.php <csv-pfad> [--dry-run]\n");
    exit(1);
}

echo ($dryRun ? "[DRY RUN] " : '') . "Importiere Kunden aus $csvPfad ...\n";

$service   = new JtlArchivImportService();
$ergebnis  = $service->importiereKunden($csvPfad, $dryRun);

echo "\n";
echo ($dryRun ? '[DRY RUN] ' : '') . "{$ergebnis['neu']} Kunden " . ($dryRun ? 'würden neu angelegt' : 'neu angelegt') . "\n";
echo "{$ergebnis['uebersprungen']} bereits vorhanden (jtl_kundennummer), übersprungen\n";
echo "{$ergebnis['verknuepft']} per E-Mail bei Bestandskunden nachverknüpft\n";

if (!empty($ergebnis['fehler'])) {
    echo count($ergebnis['fehler']) . " Fehler:\n";
    foreach ($ergebnis['fehler'] as $f) {
        echo "  - $f\n";
    }
    exit(1);
}

echo "Fertig.\n";
