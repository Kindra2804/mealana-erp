<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); exit('Ungültige Anfrage.'); }

$db   = Database::getInstance();
$stmt = $db->prepare("SELECT nummer FROM picklisten WHERE id = :id");
$stmt->execute([':id' => $id]);
$pl = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pl) { http_response_code(404); exit('Pickliste nicht gefunden.'); }

$dateiname = $pl['nummer'] . '.pdf';
$dateipfad = __DIR__ . '/../../storage/picklisten/' . $dateiname;

if (!file_exists($dateipfad)) {
    http_response_code(404);
    exit('PDF nicht gefunden. Bitte Pickliste neu erstellen.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $dateiname . '"');
header('Content-Length: ' . filesize($dateipfad));
readfile($dateipfad);
exit;
