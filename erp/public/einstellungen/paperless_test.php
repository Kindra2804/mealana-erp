<?php
// Paperless: Verbindung zur Belegseite testen — mit den Werten aus dem Formular (auch ungespeichert),
// leerer Schlüssel = gespeicherten nehmen
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/PaperlessService.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'meldung' => 'Nur POST erlaubt.']);
    exit;
}

$k          = PaperlessService::konfig();
$url        = rtrim(trim($_POST['url'] ?? ''), '/') ?: $k['url'];
$schluessel = trim($_POST['schluessel'] ?? '') ?: $k['schluessel'];
$erg        = PaperlessService::test($url, $schluessel);

if (empty($erg['erfolg'])) {
    echo json_encode(['erfolg' => false, 'meldung' => $erg['fehler'] ?? 'Unbekannter Fehler.']);
    exit;
}
if (empty($erg['ordner_beschreibbar'])) {
    echo json_encode(['erfolg' => false, 'meldung' => 'Verbunden, aber der Ordner daten/ auf der Belegseite ist nicht beschreibbar.']);
    exit;
}
echo json_encode(['erfolg' => true, 'meldung' => sprintf('✓ Verbunden (Version %s, Datenbank %s, %d Belege gespeichert)',
    $erg['version'] ?? '?', $erg['datenbank'] ?? '?', (int)($erg['anzahl'] ?? 0))]);
