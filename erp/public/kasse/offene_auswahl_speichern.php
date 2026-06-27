<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST.']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['positionen'])) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Keine Positionen.']); exit;
}

$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);
$service    = new KassenService();

$result = $service->erstelleMitgeben([
    'kunden_name'   => $input['kunden_name']   ?? null,
    'kunden_id'     => $input['kunden_id']     ?? null,
    'lager_id'      => (int)($input['lager_id'] ?? 1),
    'rueckgabe_bis' => $input['rueckgabe_bis'] ?? null,
    'notiz'         => $input['notiz']         ?? null,
], $input['positionen'], $benutzerId);

echo json_encode($result);
