<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$kundeId = (int) ($_POST['kunde_id'] ?? 0);
$service = new KundenService();

$data = [
    'id'           => (int) ($_POST['id'] ?? 0),
    'kunde_id'     => $kundeId,
    'adresstyp'    => $_POST['adresstyp']  ?? 'haupt',
    'ist_standard' => isset($_POST['ist_standard']) ? 1 : 0,
    'land'         => $_POST['land']       ?? 'AT',
    'firma'        => trim($_POST['firma']       ?? ''),
    'vorname'      => trim($_POST['vorname']     ?? ''),
    'nachname'     => trim($_POST['nachname']    ?? ''),
    'strasse'      => trim($_POST['strasse']     ?? ''),
    'hausnummer'   => trim($_POST['hausnummer']  ?? ''),
    'plz'          => trim($_POST['plz']         ?? ''),
    'ort'          => trim($_POST['ort']         ?? ''),
    'zusatz'       => trim($_POST['zusatz']      ?? ''),
];

foreach ($data as &$val) {
    if ($val === '') $val = null;
}
unset($val);
$data['id']           = (int) ($_POST['id'] ?? 0);
$data['kunde_id']     = $kundeId;
$data['ist_standard'] = isset($_POST['ist_standard']) ? 1 : 0;

$result = $service->adresseAktualisieren($data);

if (!$result['erfolg']) {
    $_SESSION['fehler'] = $result['fehler'];
}

header('Location: detail.php?id=' . $kundeId . '&tab=adressen');
exit;
