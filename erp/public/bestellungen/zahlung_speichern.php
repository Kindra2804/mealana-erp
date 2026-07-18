<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';

$id = (int)($_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/bestellungen/detail.php?id=' . $id);
    exit;
}

$betrag        = (float)str_replace(',', '.', $_POST['betrag'] ?? '0');
$art           = $_POST['art'] ?? 'ueberweisung';
$buchungsdatum = $_POST['buchungsdatum'] ?? date('Y-m-d');
$notiz         = trim($_POST['notiz'] ?? '') ?: null;

$service = new BestellungService();
$result  = $service->bucheZahlung($id, $betrag, $art, $buchungsdatum, $notiz);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Zahlung gebucht.';
} else {
    $_SESSION['fehler'] = $result['fehler'];
}

header('Location: ' . BASE_PATH . '/bestellungen/detail.php?id=' . $id);
