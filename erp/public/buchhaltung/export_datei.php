<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/core/logger.php';
require_once __DIR__ . '/../../src/modules/buchhaltung/BuchhaltungExportService.php';
require_once __DIR__ . '/../../src/modules/buchhaltung/DatevFormatter.php';

$db     = Database::getInstance();
$format = $_GET['format'] ?? 'csv';
$von    = $_GET['von'] ?? date('Y-m-01');
$bis    = $_GET['bis'] ?? date('Y-m-t');

if (!in_array($format, ['csv', 'datev'], true)) {
    http_response_code(400);
    exit('Ungültiges Format');
}

$service   = new BuchhaltungExportService();
$ergebnis  = $service->sammleZeitraum($von, $bis);

$dateiVon = date('Ymd', strtotime($von));
$dateiBis = date('Ymd', strtotime($bis));

if ($format === 'csv') {
    $inhalt   = DatevFormatter::alsCsv($ergebnis['buchungen']);
    $dateiname = "buchungen_{$dateiVon}_{$dateiBis}.csv";
} else {
    $einstellungen = $db->query("
        SELECT schluessel, wert FROM system_einstellungen
        WHERE schluessel IN ('datev_berater_nr', 'datev_mandant_nr', 'datev_wj_beginn', 'datev_sachkontenlaenge')
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
    $firma    = $db->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'firmenname'")->fetchColumn();
    $inhalt   = DatevFormatter::alsDatev($ergebnis['buchungen'], $einstellungen, $von, $bis, $firma ?: 'MeaLana');
    $dateiname = "EXTF_Buchungsstapel_{$dateiVon}_{$dateiBis}.csv";
}

Logger::log('buchhaltung.export', null, null, ['format' => $format, 'von' => $von, 'bis' => $bis, 'zeilen' => count($ergebnis['buchungen'])]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $dateiname . '"');
header('Content-Length: ' . strlen($inhalt));
echo $inhalt;
