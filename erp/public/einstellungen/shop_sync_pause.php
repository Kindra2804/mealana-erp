<?php
/**
 * Setzt/löscht shops.sync_pausiert -- Pause-Schalter für den 15-Minuten-Cron
 * (cron/shop_sync.php), gesteuert über die Shop-Synchronisierung-Seite.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/core/logger.php';
require_once __DIR__ . '/../../src/modules/shop/ShopSyncRepository.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'meldung' => 'Nur POST erlaubt.']);
    exit;
}

$shopId    = (int)($_POST['shop_id'] ?? 0);
$pausiert  = ($_POST['pausiert'] ?? '0') === '1';

$repo = new ShopSyncRepository();
$shop = null;
foreach ($repo->findAktiveShops() as $s) {
    if ((int)$s['id'] === $shopId) {
        $shop = $s;
        break;
    }
}
if (!$shop) {
    echo json_encode(['erfolg' => false, 'meldung' => 'Shop nicht gefunden.']);
    exit;
}

$repo->setSyncPausiert($shopId, $pausiert);

Logger::log('shop.sync_pause_geaendert', 'shops', $shopId, [
    'shop'     => $shop['slug'],
    'pausiert' => $pausiert,
], null, 'info');

echo json_encode(['erfolg' => true]);
