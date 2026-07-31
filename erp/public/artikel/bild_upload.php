<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/BilderRepository.php';
require_once __DIR__ . '/../../src/modules/artikel/BildVerarbeitung.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

// PHP leert $_POST/$_FILES komplett ohne jeden Fehlercode, wenn die Gesamtgröße des
// Requests post_max_size übersteigt — ohne diesen Check wäre die Meldung unten
// unten irreführend "Ungültige Artikel-ID" statt der eigentlichen Ursache.
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Datei zu groß für die Server-Konfiguration (post_max_size in php.ini).']);
    exit;
}

$artikelId = (int)($_POST['artikel_id'] ?? 0);
if ($artikelId <= 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Artikel-ID']);
    exit;
}

if (empty($_FILES['bild']) || $_FILES['bild']['error'] !== UPLOAD_ERR_OK) {
    $fehlerCode = $_FILES['bild']['error'] ?? -1;
    echo json_encode(['erfolg' => false, 'fehler' => 'Upload-Fehler Code ' . $fehlerCode]);
    exit;
}

// Fehlende PHP-Erweiterungen sollen eine klare Fehlermeldung liefern statt eines
// unbehandelten Fatal Errors ("Call to undefined function") mitten in der
// JSON-Antwort — das Frontend zeigt sonst nur generisch "Netzwerkfehler".
if (!extension_loaded('gd')) {
    echo json_encode(['erfolg' => false, 'fehler' => 'PHP-GD-Erweiterung ist auf diesem Server nicht aktiviert.']);
    exit;
}
if (!extension_loaded('fileinfo')) {
    echo json_encode(['erfolg' => false, 'fehler' => 'PHP-Fileinfo-Erweiterung ist auf diesem Server nicht aktiviert.']);
    exit;
}

$file     = $_FILES['bild'];
$maxBytes = 10 * 1024 * 1024; // 10 MB

if ($file['size'] > $maxBytes) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Datei zu groß (max. 10 MB)']);
    exit;
}

// MIME-Typ prüfen (nicht nur Dateiendung)
$mimeTyp = mime_content_type($file['tmp_name']);
if (!in_array($mimeTyp, BildVerarbeitung::ERLAUBTE_MIMES)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur JPG, PNG und WEBP erlaubt']);
    exit;
}

// Upload-Ordner anlegen
$uploadDir = __DIR__ . '/../uploads/artikel/' . $artikelId . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Eindeutigen Dateinamen generieren
$endung    = 'jpg'; // Wir speichern immer als JPEG (außer PNG mit Transparenz)
$dateiname = 'bild_' . time() . '_' . random_int(1000, 9999) . '.' . $endung;
$zielpfad  = $uploadDir . $dateiname;

// Bild verkleinern und als JPEG speichern (PHP GD)
$verkleinert = BildVerarbeitung::verkleinereUndSpeichere($file['tmp_name'], $mimeTyp, $zielpfad);
if (!$verkleinert) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Bild konnte nicht verarbeitet werden']);
    exit;
}

// DB-Eintrag
$repo  = new BilderRepository();
$bildId = $repo->insert($artikelId, $dateiname);

echo json_encode([
    'erfolg'    => true,
    'bild_id'   => $bildId,
    'dateiname' => $dateiname,
    'url'       => BASE_PATH . '/uploads/artikel/' . $artikelId . '/' . $dateiname,
]);

