<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelRepository.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$artikelId = (int)($_POST['artikel_id'] ?? 0);
if ($artikelId <= 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Artikel-ID']);
    exit;
}

$repo    = new ArtikelRepository();
$artikel = $repo->findById($artikelId);
if ($artikel === false || empty($artikel['download_dateiname'])) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Keine Datei vorhanden']);
    exit;
}

$pfad = __DIR__ . '/../uploads/downloads/' . $artikelId . '/' . $artikel['download_dateiname'];
if (is_file($pfad)) {
    unlink($pfad);
}

$repo->updateDownloadDatei($artikelId, null);

echo json_encode(['erfolg' => true]);
