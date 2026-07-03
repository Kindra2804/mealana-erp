<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/bestellungen/liste.php');
    exit;
}

$service = new BestellungService();
$result  = $service->rechnungSpeichern($_POST);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Rechnungsdaten gespeichert.';
} else {
    $_SESSION['fehler'] = $result['fehler'];
}

header('Location: ' . BASE_PATH . '/bestellungen/detail.php?id=' . (int)$_POST['id']);
exit;
