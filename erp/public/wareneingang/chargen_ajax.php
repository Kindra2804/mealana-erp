<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/wareneingang/WareneingangService.php';
header('Content-Type: application/json');

$artikelId = (int)($_GET['artikel_id'] ?? 0);
if (!$artikelId) { echo json_encode([]); exit; }

$service = new WareneingangService();
echo json_encode($service->getChargenFuerArtikel($artikelId));
