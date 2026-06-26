<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['fehler' => 'Nur POST erlaubt']);
    exit;
}

$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$artikelIds  = array_filter(array_map('intval', $body['ids'] ?? []), fn($id) => $id > 0);
$kategorieId = (int)($body['kategorie_id'] ?? 0);

if (empty($artikelIds)) {
    echo json_encode(['fehler' => 'Keine Artikel ausgewählt']);
    exit;
}
if ($kategorieId <= 0) {
    echo json_encode(['fehler' => 'Keine Kategorie ausgewählt']);
    exit;
}

$service = new ArtikelService();
$service->bulkAddKategorie(array_values($artikelIds), $kategorieId);

echo json_encode(['erfolg' => true, 'anzahl' => count($artikelIds)]);
