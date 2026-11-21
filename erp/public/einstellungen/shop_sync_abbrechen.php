<?php
/**
 * „⏹ Abbrechen“ für einen laufenden Komplettabgleich (Einstellungen → Kanäle).
 *
 * Legt eine Markierungsdatei an; scripts/komplettabgleich.php prüft sie nach jedem Durchlauf und in
 * Wartepausen und beendet sich dann sauber (Sperre wird im finally gelöst). Läuft gar kein Prozess
 * mehr (Protokoll seit 10 Minuten unverändert, z.B. Prozess beendet oder Server neu gestartet), wird
 * die hängende Sperre gleich hier gelöst — früher ging das nur per SQL.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/core/Logger.php';
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
    if ((int)$s['id'] === $shopId) $shop = $s;
}
if (!$shop) {
    echo json_encode(['erfolg' => false, 'meldung' => 'Shop nicht gefunden.']);
    exit;
}
if ((int)$shop['bulk_import_aktiv'] !== 1) {
    echo json_encode(['erfolg' => true, 'meldung' => 'Es läuft gerade kein Vorgang.']);
    exit;
}

$logDir = __DIR__ . '/../../storage/shop_sync_logs';
if (!is_dir($logDir)) mkdir($logDir, 0777, true);
file_put_contents($logDir . '/abbrechen_' . $shopId . '.flag', date('Y-m-d H:i:s'));

// Hängt die Sperre ohne laufenden Prozess? → Protokoll seit 10 Minuten unverändert (oder keins)
$log = $shop['aktueller_sync_log'] ? $logDir . '/' . basename($shop['aktueller_sync_log']) : null;
$zuletzt = ($log && is_file($log)) ? filemtime($log) : 0;
if (time() - $zuletzt > 600) {
    $repo->setBulkImportAktiv($shopId, false);
    $repo->setAktuellerSyncLog($shopId, null);
    @unlink($logDir . '/abbrechen_' . $shopId . '.flag');
    Logger::log('shop.komplettabgleich_sperre_geloest', 'shops', $shopId, ['shop' => $shop['slug']], null, 'warn');
    echo json_encode(['erfolg' => true, 'meldung' => 'Es lief kein Prozess mehr — die hängende Sperre wurde gelöst.']);
    exit;
}

Logger::log('shop.komplettabgleich_abbruch', 'shops', $shopId, ['shop' => $shop['slug']], null, 'info');
echo json_encode(['erfolg' => true, 'meldung' => 'Abbruch angefordert — der Vorgang endet nach dem aktuellen Paket (kann ein paar Minuten dauern).']);
