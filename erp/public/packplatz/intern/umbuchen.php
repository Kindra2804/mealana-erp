<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/modules/lager/LagerService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Anfrage']); exit;
}

$artikelId  = (int)($_POST['artikel_id']  ?? 0);
$vonLagerId = (int)($_POST['von_lager_id'] ?? 0);
$zuLagerId  = (int)($_POST['zu_lager_id']  ?? 0);
$menge      = (float)($_POST['menge']      ?? 0);
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

$service = new LagerService();
$result  = $service->umbucheZwischenLager($artikelId, $vonLagerId, $zuLagerId, $menge, $benutzerId);

echo json_encode($result);
