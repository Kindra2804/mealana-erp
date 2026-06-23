<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$service = new BestellungService();
$result  = $service->aktualisieren($_POST);

if ($result['erfolg']) {
    $id = (int)$_POST['id'];
    foreach ($_POST['positionen'] ?? [] as $pos) {
        if (!empty($pos['artikel_id']) && !empty($pos['menge_bestellt'])) {
            $service->positionHinzufuegen($id, $pos);
        }
    }
    $_SESSION['erfolg'] = 'Bestellung aktualisiert.';
    header('Location: /mealana/bestellungen/detail.php?id=' . $id);
} else {
    $_SESSION['fehler']   = $result['fehler'];
    $_SESSION['formdata'] = $_POST;
    header('Location: /mealana/bestellungen/bearbeiten.php?id=' . (int)$_POST['id']);
}
exit;
