<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mealana/bestellungen/liste.php');
    exit;
}

$positionen = $_POST['positionen'] ?? [];
$service    = new BestellungService();
$result     = $service->anlegen($_POST, $positionen);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Bestellung gespeichert.';
    header('Location: /mealana/bestellungen/detail.php?id=' . $result['id']);
} else {
    $_SESSION['fehler']   = $result['fehler'];
    $_SESSION['formdata'] = $_POST;
    header('Location: /mealana/bestellungen/neu.php');
}
exit;
