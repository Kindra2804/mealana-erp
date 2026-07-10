<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

header('Content-Type: application/json');

$bonId      = (int)($_POST['bon_id'] ?? 0);
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

if (!$bonId) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Anfrage.']);
    exit;
}

$service = new KassenService();
$result  = $service->storniereBon($bonId, $benutzerId);

echo json_encode($result);
