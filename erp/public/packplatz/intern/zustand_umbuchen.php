<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/modules/lager/LagerService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Anfrage']); exit;
}

$vonArtikelId = (int)($_POST['von_artikel_id'] ?? 0);
$zuArtikelId  = (int)($_POST['zu_artikel_id']  ?? 0);
$vonLagerId   = (int)($_POST['von_lager_id']   ?? 0);
$zuLagerId    = (int)($_POST['zu_lager_id']    ?? 0);
$menge        = (float)($_POST['menge']        ?? 0);
$benutzerId   = (int)($_SESSION['benutzer']['id'] ?? 0);

if (!$vonArtikelId || !$zuArtikelId || !$vonLagerId || !$zuLagerId || $menge <= 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Fehlende Parameter']); exit;
}

$service = new LagerService();

// Ausgang vom Neu-Artikel
$ausg = $service->warenausgang([
    'artikel_id'  => $vonArtikelId,
    'lager_id'    => $vonLagerId,
    'menge'       => $menge,
    'referenz'    => 'Zustandsumbuchung',
    'notiz'       => 'Umgebucht zu Zustandsartikel ID ' . $zuArtikelId,
    'benutzer_id' => $benutzerId,
]);
if (!$ausg['erfolg']) { echo json_encode($ausg); exit; }

// Eingang zum Zustandsartikel
$eing = $service->wareneingang([
    'artikel_id'  => $zuArtikelId,
    'lager_id'    => $zuLagerId,
    'menge'       => $menge,
    'charge'      => null,
    'referenz'    => 'Zustandsumbuchung',
    'notiz'       => 'Umgebucht von Artikel ID ' . $vonArtikelId,
    'benutzer_id' => $benutzerId,
]);

echo json_encode($eing);
