<?php
/**
 * Shop-Slider: Slide anlegen/ändern inkl. Bild-Upload (multipart). Antwort JSON {erfolg, id | fehler[]}.
 * Felder siehe SliderRepository::FELDER; Bilder: bild_desktop, bild_mobil, bild_mobil_entfernen=1.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/slider/SliderService.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => ['Nur POST erlaubt']]);
    exit;
}
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    echo json_encode(['erfolg' => false, 'fehler' => ['Bild zu groß für die Server-Einstellung (post_max_size in php.ini).']]);
    exit;
}

$service  = new SliderService();
$ergebnis = $service->speichere($_POST);
if (!$ergebnis['erfolg']) {
    echo json_encode($ergebnis);
    exit;
}

$id = (int)$ergebnis['id'];
$fehler = [];
foreach (['desktop', 'mobil'] as $variante) {
    $datei = $_FILES['bild_' . $variante] ?? null;
    if ($datei && $datei['error'] !== UPLOAD_ERR_NO_FILE) {
        $r = $service->speichereBild($id, $variante, $datei);
        if (!$r['erfolg']) $fehler = array_merge($fehler, $r['fehler']);
    }
}
if (!empty($_POST['bild_mobil_entfernen']) && empty($_FILES['bild_mobil']['size'])) {
    $service->entferneMobilBild($id);
}

echo json_encode($fehler
    ? ['erfolg' => false, 'id' => $id, 'fehler' => array_merge(['Die Slide wurde gespeichert, aber:'], $fehler)]
    : ['erfolg' => true, 'id' => $id]);
