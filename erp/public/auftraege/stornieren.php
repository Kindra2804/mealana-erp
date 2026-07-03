<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/auftraege/liste.php');
    exit;
}

$id    = (int)($_POST['id'] ?? 0);
$notiz = !empty($_POST['notiz']) ? trim($_POST['notiz']) : null;

if (!$id) {
    header('Location: ' . BASE_PATH . '/auftraege/liste.php');
    exit;
}

$service  = new AuftragService();
$ergebnis = $service->stornieren($id, $notiz);

if (!$ergebnis['erfolg']) {
    $_SESSION['fehler'] = $ergebnis['fehler'];
}

header('Location: ' . BASE_PATH . '/auftraege/detail.php?id=' . $id);
exit;
