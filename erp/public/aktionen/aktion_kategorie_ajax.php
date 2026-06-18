<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/aktionen/AktionenService.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$service = new AktionenService();
$aktion  = $_POST['aktion'] ?? '';

if ($aktion === 'hinzufuegen') {
    $aktionId   = (int)($_POST['aktion_id'] ?? 0);
    $kategorieId = (int)($_POST['kategorie_id'] ?? 0);
    $von        = $_POST['von'] ?? '';
    $bis        = $_POST['bis'] ?? '';
    echo json_encode($service->addKategorie($aktionId, $kategorieId, $von, $bis));
    exit;
}

if ($aktion === 'entfernen') {
    $akId = (int)($_POST['ak_id'] ?? 0);
    echo json_encode($service->removeKategorie($akId));
    exit;
}

echo json_encode(['erfolg' => false, 'fehler' => 'Unbekannte Aktion']);
