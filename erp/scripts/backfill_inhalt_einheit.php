<?php
/**
 * Einmaliges Backfill-Werkzeug: trägt `inhalt_einheit` bei bereits importierten
 * Artikeln nach, bei denen JtlVaterKindImportService.php sie bis 2026-08-03
 * fälschlich auf NULL gesetzt hat (die Spalte "Einheit Bezugsmenge" im
 * JTL-Export wurde beim Import nie gelesen -- Fix im Import-Service selbst ist
 * bereits drin, betrifft aber nur künftige Importe). Ohne `inhalt_einheit`
 * überspringt `ShopSyncService::baueGrundpreisFelder()` den Grundpreis für
 * diesen Artikel komplett.
 *
 * Liest alle Artikelstammdaten-CSVs unter import/erledigt/ (jede Datei, die
 * sowohl "Artikelnummer" als auch "Einheit Bezugsmenge" als Spalte hat -- die
 * Bilder-/Kategorien-/Attribute-/Variationskombinationen-Exporte im selben
 * Ordner haben andere Spalten und werden übersprungen), baut daraus eine
 * Artikelnummer -> Einheit-Zuordnung, und trägt sie NUR bei Artikeln nach, die
 * aktuell noch leer sind -- idempotent, überschreibt nie einen bereits
 * gesetzten Wert. Bumpt `aktualisiert_am`, damit der nächste Sync (Cron oder
 * komplettabgleich.php) den Grundpreis automatisch nachzieht.
 *
 * Aufruf:
 *   php scripts/backfill_inhalt_einheit.php [--dry-run]
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/modules/import/JtlCsvReader.php';

$dryRun = in_array('--dry-run', $argv, true);

$importOrdner = __DIR__ . '/../../import/erledigt';
if (!is_dir($importOrdner)) {
    fwrite(STDERR, "Ordner nicht gefunden: $importOrdner\n");
    exit(1);
}

// Rekursiv, da einzelne Hersteller ihre CSVs in Unterordnern liegen haben (z.B. BorgoDePazzi/).
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($importOrdner, FilesystemIterator::SKIP_DOTS));
$csvDateien = [];
foreach ($rii as $datei) {
    if (strtolower($datei->getExtension()) === 'csv') {
        $csvDateien[] = $datei->getPathname();
    }
}

$einheitProArtikelnummer   = [];
$stammdatenDateienGefunden = 0;

foreach ($csvDateien as $pfad) {
    $zeilen = JtlCsvReader::lese($pfad);
    if (empty($zeilen)) continue;
    if (!array_key_exists('Artikelnummer', $zeilen[0]) || !array_key_exists('Einheit Bezugsmenge', $zeilen[0])) {
        continue; // kein Stammdaten-Export, überspringen
    }
    $stammdatenDateienGefunden++;
    foreach ($zeilen as $row) {
        $nr      = trim($row['Artikelnummer'] ?? '');
        $einheit = trim($row['Einheit Bezugsmenge'] ?? '');
        if ($nr === '' || $einheit === '') continue;
        // Erste gefundene, nicht-leere Einheit gewinnt -- eine Artikelnummer sollte nur
        // in einer Stammdaten-Datei vorkommen.
        if (!isset($einheitProArtikelnummer[$nr])) {
            $einheitProArtikelnummer[$nr] = $einheit;
        }
    }
}

echo count($csvDateien) . " CSV-Dateien durchsucht, $stammdatenDateienGefunden davon als Artikelstammdaten erkannt.\n";
echo count($einheitProArtikelnummer) . " Artikelnummern mit bekannter Einheit Bezugsmenge gesammelt.\n\n";

$db = Database::getInstance();
$offeneArtikel = $db->query("
    SELECT id, artikelnummer
    FROM artikel
    WHERE inhalt_einheit IS NULL OR inhalt_einheit = ''
")->fetchAll();

$update = $db->prepare("UPDATE artikel SET inhalt_einheit = :einheit, aktualisiert_am = NOW() WHERE id = :id");

$aktualisiert = 0;
$ohneTreffer  = [];

foreach ($offeneArtikel as $artikel) {
    $nr = $artikel['artikelnummer'];
    if (!isset($einheitProArtikelnummer[$nr])) {
        $ohneTreffer[] = $nr;
        continue;
    }
    if (!$dryRun) {
        $update->execute(['einheit' => $einheitProArtikelnummer[$nr], 'id' => $artikel['id']]);
    }
    $aktualisiert++;
}

echo ($dryRun ? '[DRY RUN] ' : '') . "$aktualisiert Artikel " . ($dryRun ? 'würden aktualisiert' : 'aktualisiert') . ".\n";
if (!empty($ohneTreffer)) {
    echo count($ohneTreffer) . " Artikel ohne Treffer in den CSVs (keine Änderung, ggf. von Hand nachtragen):\n";
    foreach ($ohneTreffer as $nr) {
        echo "  - $nr\n";
    }
}
