<?php
/**
 * Startet scripts/erstbefuellung_bilder.php als Hintergrundprozess -- Web-UI-
 * Variante des Kommandozeilen-Befehls aus dem Infokasten der Shop-Sync-Seite
 * (gedacht für den Fall, dass Jacky nicht greifbar ist und jemand anderes
 * ohne CMD-Kenntnisse die Bilder-Verknüpfung anstoßen muss).
 *
 * Gleicher Hintergrundprozess-Mechanismus + dieselbe bulk_import_aktiv-Sperre
 * wie shop_sync_start.php -- die beiden Skripte dürfen für denselben Shop nie
 * gleichzeitig laufen, darum reicht ein gemeinsames Lock-Flag. Der Fortschritt
 * wird bewusst über den bestehenden shop_sync_status.php-Endpunkt abgefragt
 * (der prüft nur bulk_import_aktiv + liest die übergebene Log-Datei -- beiden
 * Skripten völlig egal, welches davon gerade läuft).
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

$shopId   = (int)($_POST['shop_id'] ?? 0);
$basisUrl = trim($_POST['basis_url'] ?? '');

if ($basisUrl === '') {
    echo json_encode(['erfolg' => false, 'meldung' => 'Bitte zuerst die Bilder-Basis-URL eintragen.']);
    exit;
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
    echo json_encode(['erfolg' => false, 'meldung' => 'Für diesen Shop läuft bereits ein Komplettabgleich/eine Bilder-Verknüpfung.']);
    exit;
}

$repo->setBilderBasisUrl($shopId, $basisUrl);

// Sofort setzen, gleicher Grund wie in shop_sync_start.php: verhindert einen
// doppelten Start in der kurzen Zeitspanne, bevor der Hintergrundprozess
// tatsächlich zu laufen beginnt.
$repo->setBulkImportAktiv($shopId, true);

$logDir = __DIR__ . '/../../storage/shop_sync_logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logName = $shop['slug'] . '_bilder_ftp_' . date('Ymd_His') . '.log';
$logPfad = $logDir . '/' . $logName;

$phpBinary  = 'C:\\xampp\\php\\php.exe';
$skriptPfad = realpath(__DIR__ . '/../../scripts/erstbefuellung_bilder.php');

$befehl = 'start /B "" '
    . escapeshellarg($phpBinary) . ' '
    . escapeshellarg($skriptPfad) . ' '
    . escapeshellarg($shop['slug']) . ' '
    . escapeshellarg($basisUrl) . ' '
    . '> ' . escapeshellarg($logPfad) . ' 2>&1';

pclose(popen($befehl, 'r'));

Logger::log('shop.bilder_ftp_gestartet', 'shops', $shopId, [
    'shop'      => $shop['slug'],
    'basis_url' => $basisUrl,
], null, 'info');

echo json_encode(['erfolg' => true, 'log' => $logName]);
