<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erfolg' => false, 'fehler' => 'nur POST erlaubt']);
    exit;
}

$service = new ArtikelService();

$name     = $_POST['name']      ?? '';
$parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== ''
    ? (int)$_POST['parent_id']
    : null;
$result = $service->createKategorie($name, $parentId);

echo json_encode($result);
