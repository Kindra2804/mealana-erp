<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/hersteller/HerstellerService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => ['Nur POST erlaubt']]);
    exit;
}

$service = new HerstellerService();
$datei   = (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE)
    ? $_FILES['logo']
    : null;

echo json_encode($service->save($_POST, $datei));
