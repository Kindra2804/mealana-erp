<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelRepository.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

// PHP leert $_POST/$_FILES komplett ohne jeden Fehlercode, wenn die Gesamtgröße des
// Requests post_max_size übersteigt (gleiches Muster wie bild_upload.php).
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Datei zu groß für die Server-Konfiguration (post_max_size in php.ini).']);
    exit;
}

$artikelId = (int)($_POST['artikel_id'] ?? 0);
if ($artikelId <= 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Artikel-ID']);
    exit;
}

if (empty($_FILES['datei']) || $_FILES['datei']['error'] !== UPLOAD_ERR_OK) {
    $fehlerCode = $_FILES['datei']['error'] ?? -1;
    echo json_encode(['erfolg' => false, 'fehler' => 'Upload-Fehler Code ' . $fehlerCode]);
    exit;
}

if (!extension_loaded('fileinfo')) {
    echo json_encode(['erfolg' => false, 'fehler' => 'PHP-Fileinfo-Erweiterung ist auf diesem Server nicht aktiviert.']);
    exit;
}

$file     = $_FILES['datei'];
$maxBytes = 30 * 1024 * 1024; // 30 MB

if ($file['size'] > $maxBytes) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Datei zu groß (max. 30 MB)']);
    exit;
}

// MIME-Typ prüfen (nicht nur Dateiendung) -- ZIP-Dateien werden von manchen
// Systemen als application/x-zip-compressed statt application/zip erkannt.
$erlaubteMimes = ['application/pdf', 'application/zip', 'application/x-zip-compressed'];
$mimeTyp = mime_content_type($file['tmp_name']);
if (!in_array($mimeTyp, $erlaubteMimes, true)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur PDF und ZIP erlaubt']);
    exit;
}

$repo    = new ArtikelRepository();
$artikel = $repo->findById($artikelId);
if ($artikel === false) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Artikel nicht gefunden']);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/downloads/' . $artikelId . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Alte Datei entfernen -- es gibt immer nur eine Datei pro Download-Artikel.
if (!empty($artikel['download_dateiname'])) {
    $alterPfad = $uploadDir . $artikel['download_dateiname'];
    if (is_file($alterPfad)) {
        unlink($alterPfad);
    }
}

$endung = $mimeTyp === 'application/pdf' ? 'pdf' : 'zip';
$dateiname = 'download_' . time() . '_' . random_int(1000, 9999) . '.' . $endung;
$zielpfad  = $uploadDir . $dateiname;

if (!move_uploaded_file($file['tmp_name'], $zielpfad)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Datei konnte nicht gespeichert werden']);
    exit;
}

$repo->updateDownloadDatei($artikelId, $dateiname);

echo json_encode([
    'erfolg'    => true,
    'dateiname' => $dateiname,
    'url'       => BASE_PATH . '/uploads/downloads/' . $artikelId . '/' . rawurlencode($dateiname),
]);
