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
require_once __DIR__ . '/shop_sync_aktivitaeten_render.php';

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

// Kein Log-Name vom Client mitgeschickt (z.B. Seite während des Laufs neu
// geladen) -- in der DB nachsehen, dort hat shop_sync_start.php ihn beim
// Start hinterlegt (Migration 161).
if ($log === '' && !empty($shop['aktueller_sync_log'])) {
    $log = basename($shop['aktueller_sync_log']);
}

$logPfad = __DIR__ . '/../../storage/shop_sync_logs/' . $log;
$logTail = '';
if ($log !== '' && is_file($logPfad)) {
    // Reicht für eine Fortschrittsanzeige locker -- die Datei wächst nur um
    // wenige Zeilen alle paar Sekunden, komplette Datei lesen ist hier okay.
    $zeilen  = file($logPfad, FILE_IGNORE_NEW_LINES);
    $logTail = implode("\n", array_slice($zeilen, -30));
}

$db = Database::getInstance();
$laeufeStmt = $db->prepare("
    SELECT aktion, details, erstellt_am, stufe
    FROM aktivitaeten
    WHERE referenz_tabelle = 'shops' AND referenz_id = :shop_id
      AND aktion IN ('shop.sync_lauf', 'shop.cron_fehler', 'shop.komplettabgleich_gestartet', 'shop.bilder_ftp_gestartet')
    ORDER BY erstellt_am DESC
    LIMIT 5
");
$laeufeStmt->execute(['shop_id' => $shopId]);

echo json_encode([
    'erfolg'            => true,
    'laeuft'            => (int)$shop['bulk_import_aktiv'] === 1,
    'log'               => $log,
    'log_tail'          => $logTail,
    'aktivitaeten_html' => renderShopSyncAktivitaetenZeilen($laeufeStmt->fetchAll(PDO::FETCH_ASSOC)),
]);
