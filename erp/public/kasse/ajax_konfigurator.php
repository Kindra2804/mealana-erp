<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/konfigurator/KonfiguratorService.php';
header('Content-Type: application/json');

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$artikelId = (int)($input['artikel_id'] ?? 0);
$wertIds   = array_map('intval', $input['wert_ids'] ?? []);

if ($artikelId <= 0) {
    echo json_encode(['erfolg' => false, 'fehler' => ['Ungültiger Artikel']]);
    exit;
}

$service = new KonfiguratorService();
echo json_encode($service->berechnePreis($artikelId, $wertIds));
