<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';

header('Content-Type: application/json; charset=utf-8');

$suche   = trim($_GET['q'] ?? '');
$service = new AuftragService();

if (strlen($suche) < 2) {
    echo json_encode([]);
    exit;
}

$artikel = $service->getArtikelFuerSuche($suche);
echo json_encode($artikel);
