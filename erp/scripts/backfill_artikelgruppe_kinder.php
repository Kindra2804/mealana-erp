<?php
/**
 * Einmaliges Backfill-Werkzeug: trägt bei Kind-Artikeln, deren Vater eine
 * Artikelgruppe hat, aber die selbst keine eigene haben, die Vater-Gruppe
 * nach (spiegelt ArtikelRepository::propagiereZuKindern(), das beim normalen
 * Speichern in der UI automatisch mitläuft -- hier manuell nachgebildet, da
 * die betroffenen Väter seit ihrer JTL-Anlage nie einzeln gespeichert wurden).
 *
 * Gefunden 2026-08-11: Jacky bemerkte fehlende Artikelgruppen bei Artikeln,
 * die durch den JTL-Lagerbestand-Import erstmals sichtbaren Bestand bekamen --
 * betraf aber nicht nur diese, sondern 86% ALLER Kind-Artikel systemweit
 * (15.264 von 17.780), unabhängig vom Import. Vorbestehende Datenlücke, jetzt
 * nur zum ersten Mal auffällig geworden.
 *
 * Bumpt aktualisiert_am, damit der nächste Shop-Sync-Cron die Änderung sieht.
 * Idempotent (nur wo Kind.artikel_gruppe_id NULL ist).
 *
 * Aufruf:
 *   php scripts/backfill_artikelgruppe_kinder.php [--dry-run]
 */

require_once __DIR__ . '/../src/core/Database.php';

$dryRun = in_array('--dry-run', $argv, true);
$db = Database::getInstance();

$betroffen = $db->query("
    SELECT a.id, a.artikelnummer, vater.artikel_gruppe_id
    FROM artikel a
    JOIN artikel vater ON vater.id = a.vaterartikel_id
    WHERE a.artikel_gruppe_id IS NULL
      AND vater.artikel_gruppe_id IS NOT NULL
")->fetchAll();

$update = $db->prepare("UPDATE artikel SET artikel_gruppe_id = :gruppe_id, aktualisiert_am = NOW() WHERE id = :id");

$aktualisiert = 0;
foreach ($betroffen as $k) {
    if (!$dryRun) {
        $update->execute(['gruppe_id' => $k['artikel_gruppe_id'], 'id' => $k['id']]);
    }
    $aktualisiert++;
}

echo count($betroffen) . " Kind-Artikel ohne eigene Artikelgruppe (Vater hat eine) gefunden.\n";
echo ($dryRun ? '[DRY RUN] ' : '') . "$aktualisiert Kind-Artikel " . ($dryRun ? 'würden aktualisiert' : 'aktualisiert') . " (Artikelgruppe = Vater-Gruppe).\n";
