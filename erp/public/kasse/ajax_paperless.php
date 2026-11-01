<?php
// Paperless an der Kasse: Bon hochladen → Link + QR-Bild (POST aktion=hochladen),
// Abruf durch den Kunden abfragen (GET aktion=status), beides mit bon_id
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/PaperlessService.php';
require_once __DIR__ . '/../../src/core/QrCode.php';

header('Content-Type: application/json');

$aktion = $_REQUEST['aktion'] ?? '';
$bonId  = (int)($_REQUEST['bon_id'] ?? 0);
if (!$bonId) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Bon fehlt.']);
    exit;
}

if ($aktion === 'hochladen' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $erg = PaperlessService::hochladen($bonId);
    if ($erg['erfolg']) $erg['qr'] = QrCode::dataUri($erg['url'], 360);
    echo json_encode($erg);
} elseif ($aktion === 'verwerfen' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    echo json_encode(['erfolg' => true, 'abgerufen' => PaperlessService::verwerfen($bonId)]);
} elseif ($aktion === 'status') {
    echo json_encode(['erfolg' => true, 'abgerufen' => PaperlessService::abgerufen($bonId)]);
} else {
    echo json_encode(['erfolg' => false, 'fehler' => 'Unbekannte Aktion.']);
}
