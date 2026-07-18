<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => ['Nur POST erlaubt']]);
    exit;
}

// Bewusst als eigener Manager-Kurzweg (Rang >= 70), nicht als normale Berechtigung —
// gleicher Schwellwert wie die Notizpflicht-Ausnahme beim Zählen selbst.
$rang = (int)($_SESSION['benutzer']['rolle_rang'] ?? 0);
if ($rang < 70) {
    echo json_encode(['erfolg' => false, 'fehler' => ['Nur ab Manager-Rang verfügbar']]);
    exit;
}

$data      = json_decode(file_get_contents('php://input'), true) ?? [];
$artikelId = (int)($data['artikel_id'] ?? 0);

if (!$artikelId) {
    echo json_encode(['erfolg' => false, 'fehler' => ['Pflichtangaben fehlen']]);
    exit;
}

echo json_encode((new ArtikelService())->markiereAuslaufartikel($artikelId));
