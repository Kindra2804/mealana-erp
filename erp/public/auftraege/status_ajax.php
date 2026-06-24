<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => ['Nur POST erlaubt']]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['erfolg' => false, 'fehler' => ['ID fehlt']]);
    exit;
}

$service = new AuftragService();

$felder = [];
$erlaubt = ['zahlungsstatus', 'lieferstatus', 'tracking_nr', 'versanddienstleister', 'notiz_intern', 'notiz_versand'];
foreach ($erlaubt as $f) {
    if (isset($_POST[$f])) {
        $felder[$f] = $_POST[$f];
    }
}

$notiz    = !empty($_POST['notiz']) ? trim($_POST['notiz']) : null;
$ergebnis = $service->statusAktualisieren($id, $felder, $notiz);

echo json_encode($ergebnis);
