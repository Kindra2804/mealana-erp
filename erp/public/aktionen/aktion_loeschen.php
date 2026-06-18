<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/aktionen/AktionenService.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$service = new AktionenService();
$id      = (int)($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige ID']); exit; }
echo json_encode($service->delete($id));
