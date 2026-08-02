<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/shop/ShopSyncRepository.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$kategorieId   = (int) ($_POST['kategorie_id'] ?? 0);
$shopId        = (int) ($_POST['shop_id'] ?? 0);
$ausgeschlossen = ($_POST['ausgeschlossen'] ?? '0') === '1';

if ($kategorieId <= 0 || $shopId <= 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Kategorie- oder Shop-ID']);
    exit;
}

(new ShopSyncRepository())->setKategorieAusschluss($kategorieId, $shopId, $ausgeschlossen);

echo json_encode(['erfolg' => true]);
