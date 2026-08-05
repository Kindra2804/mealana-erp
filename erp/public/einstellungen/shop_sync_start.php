<?php
/**
 * Startet scripts/komplettabgleich.php als eigenständigen Hintergrundprozess.
 *
 * Ein Web-Request kann nicht stundenlang laufen -- darum wird hier NICHT auf
 * das Ergebnis gewartet, sondern der PHP-CLI-Prozess über "start /B" losgelöst
 * gestartet (Windows-typisches Muster: cmd.exe kehrt sofort zurück, der
 * eigentliche php.exe-Prozess läuft unabhängig weiter). Fortschritt wird über
 * shop_sync_status.php aus der Log-Datei ausgelesen.
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

$shopId       = (int)($_POST['shop_id'] ?? 0);
$batchGroesse = (int)($_POST['batch_groesse'] ?? 200);
$mitBildern   = ($_POST['mit_bildern'] ?? '1') === '1';

if ($batchGroesse < 1) {
    $batchGroesse = 200;
}

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
    echo json_encode(['erfolg' => false, 'meldung' => 'Für diesen Shop läuft bereits ein Komplettabgleich.']);
    exit;
}

// Sofort setzen (nicht erst dem gestarteten Skript überlassen) -- sonst könnte
// ein zweiter Klick in der kurzen Zeitspanne, bevor der Hintergrundprozess
// tatsächlich zu laufen beginnt, denselben Shop ein zweites Mal starten.
$repo->setBulkImportAktiv($shopId, true);

$logDir = __DIR__ . '/../../storage/shop_sync_logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logName = $shop['slug'] . '_' . date('Ymd_His') . '.log';
$logPfad = $logDir . '/' . $logName;

$phpBinary   = 'C:\\xampp\\php\\php.exe';
$skriptPfad  = realpath(__DIR__ . '/../../scripts/komplettabgleich.php');

$befehl = 'start /B "" '
    . escapeshellarg($phpBinary) . ' '
    . escapeshellarg($skriptPfad) . ' '
    . escapeshellarg($shop['slug']) . ' '
    . escapeshellarg((string)$batchGroesse) . ' '
    . ($mitBildern ? '' : escapeshellarg('ohne-bilder') . ' ')
    . '> ' . escapeshellarg($logPfad) . ' 2>&1';

pclose(popen($befehl, 'r'));

Logger::log('shop.komplettabgleich_gestartet', 'shops', $shopId, [
    'shop'          => $shop['slug'],
    'batch_groesse' => $batchGroesse,
    'mit_bildern'   => $mitBildern,
], null, 'info');

echo json_encode(['erfolg' => true, 'log' => $logName]);
