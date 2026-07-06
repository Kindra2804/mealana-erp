<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/BfrService.php';
header('Content-Type: application/json');

$kasseId = (int)($_POST['kasse_id'] ?? 1);
$aktion  = $_POST['aktion'] ?? 'einzeln';

$service = new BfrService();

$ergebnis = $aktion === 'kassenstart'
    ? $service->pruefeKassenstart($kasseId)
    : $service->pruefeVorBuchungEinzeln($kasseId);

echo json_encode($ergebnis);
