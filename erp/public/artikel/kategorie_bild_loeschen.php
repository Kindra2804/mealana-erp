<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/KategorieRepository.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$kategorieId = (int)($_POST['kategorie_id'] ?? 0);
if ($kategorieId <= 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Kategorie-ID']);
    exit;
}

$repo      = new KategorieRepository();
$dateiname = $repo->findBildPfad($kategorieId);

if ($dateiname) {
    $dateipfad = __DIR__ . '/../uploads/kategorien/' . $kategorieId . '/' . $dateiname;
    if (file_exists($dateipfad)) {
        unlink($dateipfad);
    }
}

$repo->updateBild($kategorieId, null);

echo json_encode(['erfolg' => true]);
