<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$service = new KundenService();

$data = [
    'ist_firma'           => isset($_POST['ist_firma']) ? 1 : 0,
    'vorname'             => trim($_POST['vorname']          ?? ''),
    'nachname'            => trim($_POST['nachname']         ?? ''),
    'firmenname'          => trim($_POST['firmenname']       ?? ''),
    'email'               => trim($_POST['email']            ?? ''),
    'telefon'             => trim($_POST['telefon']          ?? ''),
    'mobil'               => trim($_POST['mobil']            ?? ''),
    'geburtsdatum'        => trim($_POST['geburtsdatum']     ?? ''),
    'uid_nummer'          => trim($_POST['uid_nummer']       ?? ''),
    'kreditlimit'         => trim($_POST['kreditlimit']      ?? ''),
    'kundengruppe_id'     => trim($_POST['kundengruppe_id']  ?? ''),
    'zahlungsbedingung_id'=> trim($_POST['zahlungsbedingung_id'] ?? ''),
    'standardzahlungsart' => trim($_POST['standardzahlungsart'] ?? ''),
    'kundenherkunft'      => trim($_POST['kundenherkunft']   ?? 'erp'),
    'sprache'             => trim($_POST['sprache']          ?? 'de'),
    'notiz'               => trim($_POST['notiz']            ?? ''),
    // Adresse
    'strasse'             => trim($_POST['strasse']          ?? ''),
    'hausnummer'          => trim($_POST['hausnummer']       ?? ''),
    'plz'                 => trim($_POST['plz']              ?? ''),
    'ort'                 => trim($_POST['ort']              ?? ''),
    'land'                => trim($_POST['land']             ?? 'AT'),
];

// Leere Strings → NULL
foreach ($data as &$val) {
    if ($val === '') $val = null;
}
unset($val);
// ist_firma muss int bleiben
$data['ist_firma'] = isset($_POST['ist_firma']) ? 1 : 0;

$result = $service->anlegen($data);

if (!$result['erfolg']) {
    $_SESSION['fehler']   = $result['fehler'];
    $_SESSION['formdata'] = $_POST;
    header('Location: neu.php');
    exit;
}

// Newsletter-Consent eintragen wenn gewünscht
if (isset($_POST['newsletter'])) {
    $service->consentEintragen([
        'kunde_id'    => $result['id'],
        'consent_typ' => 'newsletter',
        'eingewilligt'=> 1,
        'quelle'      => 'erp_manuell',
    ]);
}

$_SESSION['erfolg'] = 'Kunde ' . $result['kundennummer'] . ' erfolgreich angelegt.';
header('Location: detail.php?id=' . $result['id']);
exit;
