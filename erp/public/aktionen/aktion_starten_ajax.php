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
$aktion  = $_POST['aktion'] ?? '';

if ($aktion === 'starten') {
    echo json_encode($service->starten($id));
    exit;
}
if ($aktion === 'stoppen') {
    echo json_encode($service->stoppen($id));
    exit;
}

echo json_encode(['erfolg' => false, 'fehler' => 'Unbekannte Aktion']);
