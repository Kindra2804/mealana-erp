<?php
/**
 * AJAX-Handler für den Kanal-Dropdown im Artikel-Formular (Shop an/aus).
 * action=toggle — eigenen Wunsch-Status für einen Shop setzen.
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
            $artikelId = (int)($input['artikel_id'] ?? 0);
            $shopId    = (int)($input['shop_id'] ?? 0);
            $aktiv     = !empty($input['aktiv']);
            if (!$artikelId || !$shopId) throw new Exception('Ungültige Daten');
            $repo->upsertZuweisung($artikelId, $shopId, $aktiv);
            echo json_encode(['erfolg' => true, 'kanaele' => $repo->findKanalStatusFuerArtikel($artikelId)]);
            break;

        default:
            echo json_encode(['fehler' => 'Unbekannte Aktion']);
    }
} catch (Exception $e) {
    echo json_encode(['fehler' => $e->getMessage()]);
}
