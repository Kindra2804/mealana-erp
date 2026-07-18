<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/inventur/InventurService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => ['Nur POST erlaubt']]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];

$laufId       = (int)($data['lauf_id'] ?? 0);
$lagerplatzId = (int)($data['lagerplatz_id'] ?? 0);
$benutzerId   = (int)($_SESSION['benutzer']['id'] ?? 0);

if (!$laufId || !$lagerplatzId) {
    echo json_encode(['erfolg' => false, 'fehler' => ['Pflichtangaben fehlen']]);
    exit;
}

echo json_encode((new InventurService())->lagerplatzWaehlen($laufId, $lagerplatzId, $benutzerId));
