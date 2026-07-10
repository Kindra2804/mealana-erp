<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/hersteller/HerstellerService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => ['Nur POST erlaubt']]);
    exit;
}

// PHP leert $_POST/$_FILES komplett ohne jeden Fehlercode, wenn die Gesamtgröße des
// Requests post_max_size übersteigt — sonst käme hier nur eine irreführende Meldung.
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    echo json_encode(['erfolg' => false, 'fehler' => ['Logo zu groß für die Server-Konfiguration (post_max_size in php.ini).']]);
    exit;
}

$service = new HerstellerService();
$datei   = (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE)
    ? $_FILES['logo']
    : null;

echo json_encode($service->update($_POST, $datei));
