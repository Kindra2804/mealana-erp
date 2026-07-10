<?php
// Web-App-Manifest, damit "Zum Startbildschirm hinzufügen" die Seite ohne Adressleiste/
// Chrome-UI öffnet (display:fullscreen). PHP statt statischer .json-Datei, weil start_url
// den ?kasse=-Parameter der aktuellen Seite mitnehmen muss — sonst würde das installierte
// Icon immer ohne Kasse starten.
require_once __DIR__ . '/../../config/bootstrap.php';

header('Content-Type: application/manifest+json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$kasseNr = trim($_GET['kasse'] ?? '');

echo json_encode([
    'name'             => 'Kundenanzeige' . ($kasseNr !== '' ? ' ' . $kasseNr : ''),
    'short_name'       => 'Kundenanzeige',
    'start_url'        => BASE_PATH . '/kundenanzeige/?kasse=' . urlencode($kasseNr),
    'scope'            => BASE_PATH . '/kundenanzeige/',
    'display'          => 'fullscreen',
    'orientation'      => 'landscape',
    'background_color' => '#0f172a',
    'theme_color'      => '#0f172a',
], JSON_UNESCAPED_SLASHES);
