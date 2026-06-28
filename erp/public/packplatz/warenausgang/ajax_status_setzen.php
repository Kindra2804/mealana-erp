<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false]); exit;
}

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$auftragId = (int)($input['auftrag_id'] ?? 0);
$status    = $input['status'] ?? '';

$erlaubt = ['kommissioniert', 'abholbereit', 'versandbereit', 'in_bearbeitung'];
if (!$auftragId || !in_array($status, $erlaubt, true)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Parameter']); exit;
}

$db = Database::getInstance();
$db->prepare("UPDATE auftraege SET lieferstatus = ?, aktualisiert_am = NOW() WHERE id = ?")
   ->execute([$status, $auftragId]);

echo json_encode(['erfolg' => true]);
