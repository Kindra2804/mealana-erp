<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/preise/PreisService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Methode']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

$service  = new PreisService();
$ergebnis = $service->speichereStaffelpreis([
    'id'               => $id > 0 ? $id : null,
    'artikel_id'       => (int) ($_POST['artikel_id'] ?? 0),
    'kundengruppen_id' => (int) ($_POST['kundengruppen_id'] ?? 0),
    'menge_ab'         => $_POST['menge_ab'] ?? '',
    'brutto_vk'        => $_POST['brutto_vk'] ?? '',
    'netto_vk'         => $_POST['netto_vk'] ?? '',
]);

echo json_encode($ergebnis);
