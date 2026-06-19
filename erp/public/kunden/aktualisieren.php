<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$service = new KundenService();

$data = [
    'id'                  => $id,
    'ist_firma'           => isset($_POST['ist_firma']) ? 1 : 0,
    'vorname'             => trim($_POST['vorname']             ?? ''),
    'nachname'            => trim($_POST['nachname']            ?? ''),
    'firmenname'          => trim($_POST['firmenname']          ?? ''),
    'email'               => trim($_POST['email']               ?? ''),
    'telefon'             => trim($_POST['telefon']             ?? ''),
    'mobil'               => trim($_POST['mobil']               ?? ''),
    'geburtsdatum'        => trim($_POST['geburtsdatum']        ?? ''),
    'uid_nummer'          => trim($_POST['uid_nummer']          ?? ''),
    'kreditlimit'         => trim($_POST['kreditlimit']         ?? ''),
    'kundengruppe_id'     => trim($_POST['kundengruppe_id']     ?? ''),
    'zahlungsbedingung_id'=> trim($_POST['zahlungsbedingung_id'] ?? ''),
    'standardzahlungsart' => trim($_POST['standardzahlungsart'] ?? ''),
    'kundenherkunft'      => trim($_POST['kundenherkunft']      ?? 'erp'),
    'sprache'             => trim($_POST['sprache']             ?? 'de'),
    'status'              => trim($_POST['status']              ?? 'aktiv'),
    'notiz'               => trim($_POST['notiz']               ?? ''),
];

foreach ($data as &$val) {
    if ($val === '') $val = null;
}
unset($val);
$data['id']       = $id;
$data['ist_firma'] = isset($_POST['ist_firma']) ? 1 : 0;

$result = $service->aktualisieren($data);

if (!$result['erfolg']) {
    $_SESSION['fehler']   = $result['fehler'];
    $_SESSION['formdata'] = $_POST;
    header('Location: bearbeiten.php?id=' . $id);
    exit;
}

$_SESSION['erfolg'] = 'Kunde erfolgreich gespeichert.';
header('Location: detail.php?id=' . $id);
exit;
