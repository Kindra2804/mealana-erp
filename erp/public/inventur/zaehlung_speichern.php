<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/inventur/InventurService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => ['Nur POST erlaubt']]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];

$laufId       = (int)($data['lauf_id'] ?? 0);
$artikelId    = (int)($data['artikel_id'] ?? 0);
$lagerId      = !empty($data['lager_id']) ? (int)$data['lager_id'] : null;
$lagerplatzId = !empty($data['lagerplatz_id']) ? (int)$data['lagerplatz_id'] : null;
$charge       = !empty($data['charge']) ? trim($data['charge']) : null;
$sollMenge    = isset($data['soll_menge']) && $data['soll_menge'] !== null ? (float)$data['soll_menge'] : null;
$istMenge     = isset($data['ist_menge']) ? (float)$data['ist_menge'] : null;
$notiz        = !empty($data['notiz']) ? trim($data['notiz']) : null;

if (!$laufId || !$artikelId || $istMenge === null) {
    echo json_encode(['erfolg' => false, 'fehler' => ['Pflichtangaben fehlen']]);
    exit;
}

echo json_encode((new InventurService())->bucheZaehlung(
    $laufId, $artikelId, $lagerId, $lagerplatzId, $charge, $istMenge, $notiz, $sollMenge
));
