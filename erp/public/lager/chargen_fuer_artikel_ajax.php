<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

header('Content-Type: application/json; charset=utf-8');

$artikelId = (int)($_GET['artikel_id'] ?? 0);
if (!$artikelId) {
    echo json_encode([]);
    exit;
}

$service = new LagerService();
echo json_encode($service->getChargenFuerArtikel($artikelId));
