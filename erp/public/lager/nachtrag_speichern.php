<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: nachtrag_liste.php');
    exit;
}

$data = $_POST;

$service = new LagerService();
$result = $service->chargeNachtragen(
    (int)$data['lagerbestand_id'],
    $data['charge'],
    (float)($data['menge'] ?? 0)
);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Charge nachgetragen!';
    header('Location: nachtrag_liste.php');
    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $data;
    header('Location: nachtrag_liste.php');
    exit;
}
