<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/MerkmaleRepository.php';
require_once __DIR__ . '/../../src/core/Logger.php';
require_once __DIR__ . '/../../src/core/Database.php';

header('Content-Type: application/json');

$input     = json_decode(file_get_contents('php://input'), true);
$artikelId = (int)($input['artikel_id'] ?? 0);
$merkmale  = $input['merkmale'] ?? [];

if (!$artikelId) {
    echo json_encode(['fehler' => 'Ungültige Artikel-ID']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();

    $db->prepare("DELETE FROM artikel_merkmale WHERE artikel_id = :aid")->execute(['aid' => $artikelId]);

    $stmt = $db->prepare("INSERT INTO artikel_merkmale (artikel_id, merkmal_id, merkmal_wert_id) VALUES (:aid, :mid, :wid)");
    foreach ($merkmale as $merkmalId => $wertIds) {
        foreach ($wertIds as $wertId) {
            $stmt->execute([
                'aid' => $artikelId,
                'mid' => (int)$merkmalId,
                'wid' => (int)$wertId,
            ]);
        }
    }

    $db->commit();
    Logger::log('artikel.merkmale_aktualisieren', 'artikel', $artikelId, ['merkmal_count' => count($merkmale)]);
    echo json_encode(['erfolg' => true]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['fehler' => $e->getMessage()]);
}
