<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/preise/PreisService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Methode']);
    exit;
}

$service  = new PreisService();
$ergebnis = $service->speichereKundengruppenPreis([
    'artikel_id'       => (int)   ($_POST['artikel_id']       ?? 0),
    'kundengruppen_id' => (int)   ($_POST['kundengruppen_id'] ?? 0),
    'brutto_vk'        => (float) ($_POST['brutto_vk']        ?? 0),
    'netto_vk'         => (float) ($_POST['netto_vk']         ?? 0),
    'gueltig_ab'       => trim($_POST['gueltig_ab']  ?? ''),
    'gueltig_bis'      => trim($_POST['gueltig_bis'] ?? ''),
]);

echo json_encode($ergebnis);
