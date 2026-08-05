<?php
/**
 * AJAX-Handler: eine einzelne Kategorie-Zuweisung eines Artikels für einen
 * bestimmten Shop aus-/einblenden (siehe artikel_kategorie_shop_ausschluss,
 * Migration 159).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/shop/ShopSyncRepository.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';
$repo   = new ShopSyncRepository();

try {
    switch ($action) {

        case 'toggle':
            $artikelId   = (int)($input['artikel_id'] ?? 0);
            $kategorieId = (int)($input['kategorie_id'] ?? 0);
            $shopId      = (int)($input['shop_id'] ?? 0);
            $ausgeschlossen = !empty($input['ausgeschlossen']);
            if (!$artikelId || !$kategorieId || !$shopId) throw new Exception('Ungültige Daten');
            $repo->setKategorieShopAusschlussFuerArtikel($artikelId, $kategorieId, $shopId, $ausgeschlossen);
            echo json_encode(['erfolg' => true]);
            break;

        default:
            echo json_encode(['fehler' => 'Unbekannte Aktion']);
    }
} catch (Exception $e) {
    echo json_encode(['fehler' => $e->getMessage()]);
}
