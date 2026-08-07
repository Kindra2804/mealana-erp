<?php
/**
 * Gleicht 'error'-markierte artikel_shops-Zeilen (ohne external_id) gegen den
 * echten WooCommerce-Stand ab. Hintergrund: ein Timeout oder ein 502 vom
 * Hosting (siehe WooCommerceClient::request()) beendet nur UNSERE Verbindung,
 * WooCommerce/WordPress verarbeitet serverseitig oft trotzdem fertig -- unser
 * Client weiß dann nur nicht, dass es geklappt hat, und markiert die Zeile
 * als 'error'. Bei jedem weiteren Sync-Versuch würde WooCommerce den erneuten
 * 'create' wegen doppelter SKU ablehnen (kein echtes Duplikat, aber eben auch
 * kein sauberer Erfolg -- die Fehlermeldung bleibt bei jedem Lauf gleich).
 *
 * Dieses Skript prüft jede betroffene Zeile per SKU-Suche gegen WooCommerce
 * und trägt echte Treffer nach (markiereSynced) -- Details siehe
 * ShopSyncService::reconciliereOffeneFehler().
 *
 * Aufruf:
 *   php scripts/reconcile_fehler.php <shop-slug>
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/logger.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncRepository.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncService.php';

ob_implicit_flush(true);

const FORTSCHRITT_SCHRITT = 10;

$shopSlug = $argv[1] ?? null;
if (!$shopSlug) {
    fwrite(STDERR, "Aufruf: php reconcile_fehler.php <shop-slug>\n");
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

// Gleiche Sperre wie komplettabgleich.php/erstbefuellung_bilder.php -- die
// drei Skripte dürfen für denselben Shop nie gleichzeitig laufen.
$repo->setBulkImportAktiv((int)$shop['id'], true);

try {
    $service = new ShopSyncService();

    $fortschritt = function (int $erledigt, int $gesamt) {
        if ($gesamt === 0) {
            return;
        }
        if ($erledigt % FORTSCHRITT_SCHRITT === 0 || $erledigt === $gesamt) {
            echo "  ... $erledigt/$gesamt geprüft\n";
        }
    };

    echo "Fehler-Abgleich für '{$shop['slug']}' gestartet.\n";
    $ergebnis = $service->reconciliereOffeneFehler($shop, $fortschritt);

    if ($ergebnis['geprueft'] === 0) {
        echo "Keine offenen Fehler-Zeilen gefunden -- nichts zu tun.\n";
    } else {
        echo "\nFertig: {$ergebnis['geprueft']} geprüft, {$ergebnis['nachgetragen']} waren tatsächlich schon in WooCommerce"
            . " (jetzt nachgetragen), {$ergebnis['weiterhin_offen']} wirklich noch offen.\n";
    }
} finally {
    $repo->setBulkImportAktiv((int)$shop['id'], false);
    $repo->setAktuellerSyncLog((int)$shop['id'], null);
}
