<?php
/**
 * Einmaliges Backfill-Werkzeug: trägt bei allen Artikeln (Vater UND Kind), die
 * noch keinen UVP haben, den aktuellen Standard-VK (Endkunden-Preis aus
 * artikel_preise, ohne SALE-Override/Aktionsmodul) als UVP nach. Grund: der
 * geplante Shop-Sync-Fix nutzt uvp künftig als regular_price in WooCommerce --
 * ohne diesen Nachtrag hätten ~2760 Bestandsartikel dort plötzlich gar keinen
 * Grundpreis mehr. Idempotent (nur wo uvp NULL/0 ist), bumpt aktualisiert_am
 * automatisch (ON UPDATE current_timestamp()), damit der nächste Shop-Sync
 * den neuen UVP-Wert an WooCommerce nachzieht.
 *
 * Aufruf:
 *   php scripts/backfill_uvp.php [--dry-run]
 */

require_once __DIR__ . '/../src/core/Database.php';

$dryRun = in_array('--dry-run', $argv, true);
$db = Database::getInstance();

$offeneArtikel = $db->query("
    SELECT id, artikelnummer
    FROM artikel
    WHERE uvp IS NULL OR uvp = 0
")->fetchAll();

$standardPreisStmt = $db->prepare("
    SELECT ap.brutto_vk
    FROM artikel_preise ap
    JOIN kundengruppen k ON k.id = ap.kundengruppen_id
    WHERE ap.artikel_id = :artikel_id
      AND k.ist_standard = 1
      AND (ap.gueltig_ab IS NULL OR ap.gueltig_ab <= NOW())
      AND (ap.gueltig_bis IS NULL OR ap.gueltig_bis >= NOW())
    ORDER BY ap.gueltig_ab DESC
    LIMIT 1
");
$update = $db->prepare("UPDATE artikel SET uvp = :uvp WHERE id = :id");

$aktualisiert = 0;
$ohnePreis    = [];

foreach ($offeneArtikel as $artikel) {
    $standardPreisStmt->execute(['artikel_id' => $artikel['id']]);
    $preis = $standardPreisStmt->fetchColumn();

    if ($preis === false || (float)$preis <= 0) {
        $ohnePreis[] = $artikel['artikelnummer'];
        continue;
    }

    if (!$dryRun) {
        $update->execute(['uvp' => $preis, 'id' => $artikel['id']]);
    }
    $aktualisiert++;
}

echo count($offeneArtikel) . " Artikel ohne UVP gefunden.\n";
echo ($dryRun ? '[DRY RUN] ' : '') . "$aktualisiert Artikel " . ($dryRun ? 'würden aktualisiert' : 'aktualisiert') . " (UVP = aktueller Standard-VK).\n";
if (!empty($ohnePreis)) {
    echo count($ohnePreis) . " Artikel ohne jeden Standard-VK (keine Änderung, UVP bleibt leer):\n";
    foreach ($ohnePreis as $nr) {
        echo "  - $nr\n";
    }
}
