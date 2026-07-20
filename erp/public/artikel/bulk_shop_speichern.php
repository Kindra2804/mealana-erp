<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/shop/ShopSyncRepository.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['fehler' => 'Nur POST erlaubt']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$artikelIds = array_filter(array_map('intval', $body['ids'] ?? []), fn($id) => $id > 0);
$shopId     = (int)($body['shop_id'] ?? 0);
$aktiv      = !empty($body['aktiv']);

if (empty($artikelIds)) {
    echo json_encode(['fehler' => 'Keine Artikel ausgewählt']);
    exit;
}
if ($shopId <= 0) {
    echo json_encode(['fehler' => 'Kein Shop ausgewählt']);
    exit;
}

$repo = new ShopSyncRepository();
foreach ($artikelIds as $artikelId) {
    $repo->upsertZuweisung($artikelId, $shopId, $aktiv);
}

echo json_encode(['erfolg' => true, 'anzahl' => count($artikelIds)]);
