<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/wareneingang/WareneingangService.php';
header('Content-Type: application/json');

$ean = trim($_GET['ean'] ?? '');
if ($ean === '') { echo json_encode(['gefunden' => false]); exit; }

$service = new WareneingangService();
echo json_encode($service->sucheNachEan($ean));
