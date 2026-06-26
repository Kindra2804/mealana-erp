<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/modules/artikel/ArtikelService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Anfrage']);
    exit;
}

$artikelId = (int)($_POST['artikel_id'] ?? 0);
$ean       = trim($_POST['ean'] ?? '');

if (!$artikelId || !$ean) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Fehlende Parameter']);
    exit;
}

if (!preg_match('/^\d{8,14}$/', $ean)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'EAN muss 8–14 Ziffern haben']);
    exit;
}

$service = new ArtikelService();
$service->speichereCode($artikelId, 'GTIN13', $ean);

echo json_encode(['erfolg' => true]);
