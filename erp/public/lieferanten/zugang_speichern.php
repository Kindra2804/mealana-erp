<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$lieferant_id = (int) ($_POST['lieferant_id'] ?? 0);

$data = [
    'lieferant_id' => $lieferant_id,
    'bezeichnung'  => trim($_POST['bezeichnung'] ?? ''),
    'url'          => trim($_POST['url'] ?? '') ?: null,
    'benutzername' => trim($_POST['benutzername'] ?? '') ?: null,
    'passwort'     => $_POST['passwort'] ?? '',
    'notizen'      => trim($_POST['notizen'] ?? '') ?: null,
];

$service = new LieferantenService();
$result  = $service->saveZugang($data);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Zugang wurde gespeichert!';
    header('Location: detail.php?id=' . $lieferant_id . '&tab=zugaenge');
    exit;
} else {
    $_SESSION['fehler']   = $result['fehler'];
    $_SESSION['formdata'] = $_POST;
    header('Location: zugang_neu.php?lieferant_id=' . $lieferant_id);
    exit;
}
