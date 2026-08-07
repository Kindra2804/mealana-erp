<?php
/**
 * Startet scripts/bilder_export_fuer_shop.php als Hintergrundprozess -- Web-UI-
 * Variante, gleicher Mechanismus wie shop_sync_start.php/
 * shop_sync_bilder_ftp_start.php. Die eigentliche Kopie ist zwar rein lokal
 * (kein Netzwerk-Call) und meist schnell, kann bei sehr vielen Artikeln aber
 * durchaus einige Minuten dauern -- darum auch hier als Hintergrundprozess
 * statt den Web-Request warten zu lassen (Risiko PHP-Timeout im Browser-Request).
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

$shopId = (int)($_POST['shop_id'] ?? 0);

$repo = new ShopSyncRepository();
$shop = null;
foreach ($repo->findAktiveShops() as $s) {
    if ((int)$s['id'] === $shopId) {
        $shop = $s;
        break;
    }
}
if (!$shop) {
    echo json_encode(['erfolg' => false, 'meldung' => 'Shop nicht gefunden oder nicht aktiv/konfiguriert.']);
    exit;
}

if ((int)$shop['bulk_import_aktiv'] === 1) {
    echo json_encode(['erfolg' => false, 'meldung' => 'Für diesen Shop läuft bereits ein anderer Vorgang.']);
    exit;
}

$repo->setBulkImportAktiv($shopId, true);

$logDir = __DIR__ . '/../../storage/shop_sync_logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logName = $shop['slug'] . '_bilder_export_' . date('Ymd_His') . '.log';
$logPfad = $logDir . '/' . $logName;

$repo->setAktuellerSyncLog($shopId, $logName);

$phpBinary  = 'C:\\xampp\\php\\php.exe';
$skriptPfad = realpath(__DIR__ . '/../../scripts/bilder_export_fuer_shop.php');

$befehl = 'start /B "" '
    . escapeshellarg($phpBinary) . ' '
    . escapeshellarg($skriptPfad) . ' '
    . escapeshellarg($shop['slug']) . ' '
    . '> ' . escapeshellarg($logPfad) . ' 2>&1';

pclose(popen($befehl, 'r'));

Logger::log('shop.bilder_export_gestartet', 'shops', $shopId, [
    'shop' => $shop['slug'],
], null, 'info');

echo json_encode(['erfolg' => true, 'log' => $logName]);
