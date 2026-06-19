<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/BilderRepository.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$bildId    = (int)($_POST['bild_id'] ?? 0);
$artikelId = (int)($_POST['artikel_id'] ?? 0);

if ($bildId <= 0 || $artikelId <= 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Parameter']);
    exit;
}

$repo  = new BilderRepository();
$bild  = $repo->findById($bildId);

if (!$bild || (int)$bild['artikel_id'] !== $artikelId) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Bild nicht gefunden']);
    exit;
}

// Datei löschen
$dateipfad = __DIR__ . '/../uploads/artikel/' . $artikelId . '/' . $bild['dateiname'];
if (file_exists($dateipfad)) {
    unlink($dateipfad);
}

$repo->delete($bildId);

echo json_encode(['erfolg' => true]);
