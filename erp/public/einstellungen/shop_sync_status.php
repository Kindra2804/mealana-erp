<?php
/**
 * Liefert den Live-Fortschritt eines per shop_sync_start.php gestarteten
 * Komplettabgleichs -- per AJAX-Polling aus der Shop-Sync-Seite aufgerufen.
 *
 * Läuft-Status kommt aus shops.bulk_import_aktiv (wird vom Skript selbst im
 * finally-Block zurückgesetzt, siehe scripts/komplettabgleich.php) -- die
 * Log-Datei selbst liefert nur den Text-Fortschritt fürs Auge, nicht die
 * verlässliche "fertig ja/nein"-Information.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/shop/ShopSyncRepository.php';

header('Content-Type: application/json');

$shopId = (int)($_GET['shop_id'] ?? 0);
$log    = basename((string)($_GET['log'] ?? ''));

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

$logPfad = __DIR__ . '/../../storage/shop_sync_logs/' . $log;
$logTail = '';
if ($log !== '' && is_file($logPfad)) {
    // Reicht für eine Fortschrittsanzeige locker -- die Datei wächst nur um
    // wenige Zeilen alle paar Sekunden, komplette Datei lesen ist hier okay.
    $zeilen  = file($logPfad, FILE_IGNORE_NEW_LINES);
    $logTail = implode("\n", array_slice($zeilen, -30));
}

echo json_encode([
    'erfolg'  => true,
    'laeuft'  => (int)$shop['bulk_import_aktiv'] === 1,
    'log_tail' => $logTail,
]);
