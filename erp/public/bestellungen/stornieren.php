<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mealana/bestellungen/liste.php');
    exit;
}

$id      = (int)($_POST['id'] ?? 0);
$service = new BestellungService();
$result  = $service->stornieren($id);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Bestellung storniert.';
} else {
    $_SESSION['fehler'] = $result['fehler'];
}

header('Location: /mealana/bestellungen/detail.php?id=' . $id);
exit;
