<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/preise/PreisService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Methode']);
    exit;
}

$id        = (int)($_POST['id']         ?? 0);
$artikelId = (int)($_POST['artikel_id'] ?? 0);

if (!$id || !$artikelId) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Fehlende Parameter']);
    exit;
}

$service  = new PreisService();
echo json_encode($service->loescheSaleOverride($id, $artikelId));
