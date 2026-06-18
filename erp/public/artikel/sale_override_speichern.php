<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/preise/PreisService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Methode']);
    exit;
}

$service  = new PreisService();
$ergebnis = $service->speichereSaleOverride([
    'id'                  => (int)   ($_POST['id']                  ?? 0) ?: null,
    'artikel_id'          => (int)   ($_POST['artikel_id']          ?? 0),
    'kundengruppen_id'    => (int)   ($_POST['kundengruppen_id']    ?? 0) ?: null,
    'brutto_vk'           => (float) ($_POST['brutto_vk']           ?? 0),
    'netto_vk'            => (float) ($_POST['netto_vk']            ?? 0),
    'preis_vorher_brutto' => trim($_POST['preis_vorher_brutto'] ?? '') !== '' ? (float)$_POST['preis_vorher_brutto'] : null,
    'gueltig_ab'          => trim($_POST['gueltig_ab']  ?? '') ?: null,
    'gueltig_bis'         => trim($_POST['gueltig_bis'] ?? '') ?: null,
    'bis_lagerstand_null' => !empty($_POST['bis_lagerstand_null']),
]);

echo json_encode($ergebnis);
