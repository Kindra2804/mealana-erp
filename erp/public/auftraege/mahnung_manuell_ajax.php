<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/MahnwesenService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$auftragId = (int)($_POST['auftrag_id'] ?? 0);
$aktion    = $_POST['aktion'] ?? '';
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

if (!$auftragId || !in_array($aktion, ['erinnerung', 'stornierung'], true)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Anfrage']);
    exit;
}

$service = new MahnwesenService();

$ergebnis = $aktion === 'erinnerung'
    ? $service->sendeErinnerung($auftragId, $benutzerId, 'manuell')
    : $service->storniere($auftragId, $benutzerId, 'manuell');

echo json_encode($ergebnis);
