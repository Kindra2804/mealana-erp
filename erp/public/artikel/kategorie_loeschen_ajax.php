<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$id                  = (int)($_POST['id'] ?? 0);
$aktion              = $_POST['aktion'] ?? 'vorschau';
$verschiebeZuParent  = (int)($_POST['verschiebe_zu_parent_id'] ?? 0) ?: null;

if (!$id) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige ID']);
    exit;
}

$service = new ArtikelService();

if ($aktion === 'vorschau') {
    echo json_encode($service->getLoeschVorschau($id));
} else {
    echo json_encode($service->loescheKategorie($id, $verschiebeZuParent));
}
