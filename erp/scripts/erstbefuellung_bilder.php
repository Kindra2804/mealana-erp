<?php
/**
 * Einmaliges Bulk-Werkzeug für die Shop-Erstbefüllung.
 *
 * Verknüpft Bilder, die vorher per FTP direkt auf den WordPress-Server
 * kopiert wurden (URL-Sideload statt langsamem Byte-Upload über die VPN-
 * Leitung -- Details siehe ShopSyncService::erstbefuellungBilderPerUrl()).
 *
 * Aufruf:
 *   php scripts/erstbefuellung_bilder.php <shop-slug> <bilder-basis-url>
 *
 * Beispiel:
 *   php scripts/erstbefuellung_bilder.php mealana https://mealana.at/wp-content/uploads/mealana-erstimport
 *
 * Voraussetzung:
 *   - Die Ordnerstruktur unter public/uploads/artikel/ wurde vorher 1:1 per
 *     FTP unter diese Basis-URL auf den WordPress-Server kopiert.
 *   - Der normale Text-Sync (cron/shop_sync.php bzw. ShopSyncService::syncShop())
 *     ist für diese Artikel schon gelaufen -- ohne WooCommerce-Produkt-ID
 *     (external_id) kann kein Bild angehängt werden.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/logger.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncRepository.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncService.php';

$shopSlug       = $argv[1] ?? null;
$bilderBasisUrl = $argv[2] ?? null;

if (!$shopSlug || !$bilderBasisUrl) {
    fwrite(STDERR, "Aufruf: php erstbefuellung_bilder.php <shop-slug> <bilder-basis-url>\n");
    exit(1);
}

$repo = new ShopSyncRepository();
$shop = null;
foreach ($repo->findAktiveShops() as $s) {
    if ($s['slug'] === $shopSlug) {
        $shop = $s;
        break;
    }
}
if (!$shop) {
    fwrite(STDERR, "Shop '$shopSlug' nicht gefunden oder nicht aktiv.\n");
    exit(1);
}

// Sperre analog zum JTL-Komplettabgleich: solange dieses Skript läuft, überspringt
// der normale 15-Minuten-Cron (cron/shop_sync.php) diesen Shop komplett -- sonst
// Race Condition (doppelter Bild-Upload, Cron überschreibt mit veraltetem Stand).
// try/finally stellt sicher, dass die Sperre auch bei einem Fehler wieder freigegeben
// wird. Falls das Skript hart abstürzt/abgebrochen wird (z.B. Strg+C): die Sperre
// bleibt dann hängen und muss von Hand zurückgesetzt werden --
//   UPDATE shops SET bulk_import_aktiv = 0 WHERE slug = '<shop-slug>';
$repo->setBulkImportAktiv((int)$shop['id'], true);
try {
    $service  = new ShopSyncService();
    $ergebnis = $service->erstbefuellungBilderPerUrl($shop, $bilderBasisUrl);
    echo "Shop '{$shop['slug']}': {$ergebnis['erfolg']} Bilder verknüpft, {$ergebnis['fehler']} Fehler\n";
} finally {
    $repo->setBulkImportAktiv((int)$shop['id'], false);
}
