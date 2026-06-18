<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/aktionen/AktionenService.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Anfrage']);
    exit;
}

$aktionId = (int)($body['aktion_id'] ?? 0);
$kgId     = (int)($body['kg_id'] ?? 1);
$preise   = $body['preise'] ?? [];

if (!$aktionId || !$kgId) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Pflichtparameter fehlen']);
    exit;
}

$service = new AktionenService();
echo json_encode($service->savePreise($aktionId, $kgId, $preise));
