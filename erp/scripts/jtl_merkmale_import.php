<?php
/**
 * Übernimmt die Artikelmerkmale aus dem JTL-Ameise-Export "Artikelmerkmale" (Migration 233 nötig).
 * Details zur Zuordnung: src/modules/import/JtlMerkmalImportService.php
 *
 * Aufruf:
 *   php scripts/jtl_merkmale_import.php <csv-pfad> [--dry-run]
 *
 * --dry-run: alles wird durchgerechnet und wieder zurückgenommen (nichts gespeichert).
 * Der vollständige Bericht (nicht gefundene Artikel, uneinheitliche Väter) landet neben der CSV
 * als <datei>_bericht.txt.
 */

ini_set('memory_limit', '1024M');

require_once __DIR__ . '/../src/modules/import/JtlMerkmalImportService.php';

$pfad = $argv[1] ?? '';
$probelauf = in_array('--dry-run', $argv, true);
if ($pfad === '' || !is_file($pfad)) {
    fwrite(STDERR, "Aufruf: php scripts/jtl_merkmale_import.php <csv-pfad> [--dry-run]\n");
    exit(1);
}

$start = microtime(true);
$b = (new JtlMerkmalImportService())->importiere($pfad, $probelauf);
$z = $b['zahlen'];

$text  = ($probelauf ? "PROBELAUF -- nichts gespeichert\n" : "IMPORT gespeichert\n") . str_repeat('=', 40) . "\n";
$text .= "Zeilen in der Datei:            {$z['zeilen']}\n";
$text .= "Artikel in der Datei:           {$z['artikel_datei']}\n";
$text .= "  davon im ERP gefunden:        {$z['artikel_gefunden']}\n";
$text .= "  nicht gefunden:               " . count($b['nicht_gefunden']) . "\n";
$text .= "Väter aus ihren Kindern befüllt: {$z['vaeter_von_kindern']} Merkmal-Einträge\n";
$text .= "Neue Merkmalwerte angelegt:     {$z['werte_neu']}\n";
$text .= "Pflegeanleitung übersprungen:   {$z['ignoriert']} Zeilen\n";
foreach ($b['unbekannt'] as $mk => $n) $text .= "Unbekanntes Merkmal \"$mk\":     $n Zeilen (nicht übernommen)\n";
$text .= sprintf("Dauer: %.1f s\n", microtime(true) - $start);

$lang  = $text;
$lang .= "\n== Väter mit uneinheitlichen Kindern (" . count($b['uneinheitlich']) . ")\n" . implode("\n", $b['uneinheitlich']) . "\n";
$lang .= "\n== Mehrere Werte bei Einzelwert-Merkmal (" . count($b['mehrfach']) . ")\n" . implode("\n", $b['mehrfach']) . "\n";
$lang .= "\n== Nicht im ERP gefunden (" . count($b['nicht_gefunden']) . ")\n" . implode("\n", $b['nicht_gefunden']) . "\n";

$berichtDatei = preg_replace('/\.csv$/i', '', $pfad) . '_bericht' . ($probelauf ? '_probelauf' : '') . '.txt';
file_put_contents($berichtDatei, $lang);

echo $text;
echo "\nUneinheitliche Väter: " . count($b['uneinheitlich']) . ", Mehrfachwerte: " . count($b['mehrfach']) . "\n";
foreach (array_slice($b['uneinheitlich'], 0, 10) as $u) echo "  $u\n";
echo "\nVollständiger Bericht: $berichtDatei\n";
