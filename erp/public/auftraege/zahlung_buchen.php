<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$auftragId    = (int)($_POST['auftrag_id'] ?? 0);
$betrag       = (float)str_replace(',', '.', $_POST['betrag'] ?? '0');
$buchungsdatum = trim($_POST['buchungsdatum'] ?? '');
$notiz        = trim($_POST['notiz'] ?? '') ?: null;

if (!$auftragId) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Auftrag-ID fehlt']);
    exit;
}
if (!$buchungsdatum || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $buchungsdatum)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültiges Buchungsdatum']);
    exit;
}

$service  = new AuftragService();
$ergebnis = $service->bucheZahlung($auftragId, $betrag, $buchungsdatum, $notiz);

echo json_encode($ergebnis);
