<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/wareneingang/WareneingangService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mealana/wareneingang/index.php');
    exit;
}

$bestellungId = (int)($_POST['bestellung_id'] ?? 0);
$service      = new WareneingangService();
$result       = $service->bucheMenge($_POST);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Eingang gebucht.';
} else {
    $_SESSION['fehler'] = $result['fehler'];
}

header('Location: /mealana/wareneingang/detail.php?bestellung_id=' . $bestellungId);
exit;
