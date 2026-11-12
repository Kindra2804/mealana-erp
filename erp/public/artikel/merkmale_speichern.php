<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/merkmale/MerkmalService.php';
require_once __DIR__ . '/../../src/core/Logger.php';

header('Content-Type: application/json');

$input     = json_decode(file_get_contents('php://input'), true);
$artikelId = (int)($input['artikel_id'] ?? 0);
$merkmale  = $input['merkmale'] ?? [];

if (!$artikelId) {
    echo json_encode(['fehler' => 'Ungültige Artikel-ID']);
    exit;
}

try {
    // Speichert, leitet "Lauflänge je 100 g" ab und vererbt bei einem Vater an alle Kinder
    $kinder = (new MerkmalService())->speichern($artikelId, $merkmale);
    Logger::log('artikel.merkmale_aktualisieren', 'artikel', $artikelId, ['merkmal_count' => count($merkmale), 'an_kinder' => $kinder]);
    echo json_encode(['erfolg' => true, 'kinder' => $kinder]);
} catch (Exception $e) {
    echo json_encode(['fehler' => $e->getMessage()]);
}
