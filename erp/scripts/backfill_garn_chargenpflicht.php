<?php
/**
 * Einmaliges Backfill-Werkzeug: setzt charge_pflicht=1 bei allen aktiven
 * Garn-Vater-/Standalone-Artikeln mit Einheit Knäuel oder Strang, die noch
 * charge_pflicht=0 haben (Jackys Entscheidung 2026-08-11 -- echte
 * Knäuel/Strang-Garne sollen Chargen-Rückverfolgung haben, Sets/Kits/Pakete/
 * Anleitungen und andere Einheiten wie Gramm/Stück bewusst nicht).
 *
 * Aktualisiert danach auch alle Kind-Varianten der betroffenen Vater-Artikel
 * (spiegelt ArtikelRepository::propagiereZuKindern(), das beim normalen
 * Speichern in der UI automatisch mitläuft -- hier manuell nachgebildet, da
 * der Bulk-Lauf nicht über ArtikelService::update() geht).
 *
 * Bumpt aktualisiert_am, damit der nächste Shop-Sync-Cron die Änderung sieht.
 * Idempotent (nur wo charge_pflicht=0 ist).
 *
 * Aufruf:
 *   php scripts/backfill_garn_chargenpflicht.php [--dry-run]
 */

require_once __DIR__ . '/../src/core/Database.php';

$dryRun = in_array('--dry-run', $argv, true);
$db = Database::getInstance();

$knaeuelStrangEinheitIds = [1, 12]; // Knäuel, Strang (siehe Tabelle `einheiten`)
$platzhalter = implode(',', array_fill(0, count($knaeuelStrangEinheitIds), '?'));

$vaterArtikel = $db->prepare("
    SELECT a.id, a.artikelnummer
    FROM artikel a
    JOIN artikel_typen at ON at.id = a.artikeltyp_id
    WHERE a.vaterartikel_id IS NULL
      AND a.aktiv = 1
      AND at.code = 'GARN'
      AND a.charge_pflicht = 0
      AND a.einheit_id IN ($platzhalter)
");
$vaterArtikel->execute($knaeuelStrangEinheitIds);
$vaterArtikel = $vaterArtikel->fetchAll();

$updateVater = $db->prepare("UPDATE artikel SET charge_pflicht = 1, aktualisiert_am = NOW() WHERE id = :id");
$updateKinder = $db->prepare("UPDATE artikel SET charge_pflicht = 1, aktualisiert_am = NOW() WHERE vaterartikel_id = :vater_id");

$aktualisiert = 0;
$kinderAktualisiert = 0;

foreach ($vaterArtikel as $artikel) {
    if (!$dryRun) {
        $updateVater->execute(['id' => $artikel['id']]);
        $updateKinder->execute(['vater_id' => $artikel['id']]);
        $kinderAktualisiert += $updateKinder->rowCount();
    }
    $aktualisiert++;
}

echo count($vaterArtikel) . " Garn-Vater/Standalone-Artikel (Knäuel/Strang, ohne Chargenpflicht) gefunden.\n";
echo ($dryRun ? '[DRY RUN] ' : '') . "$aktualisiert Artikel " . ($dryRun ? 'würden' : 'wurden') . " auf charge_pflicht=1 gesetzt.\n";
if (!$dryRun) {
    echo "$kinderAktualisiert Kind-Varianten mit aktualisiert.\n";
}
