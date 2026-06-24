<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';

header('Content-Type: application/json; charset=utf-8');

$suche = trim($_GET['q'] ?? '');
if (strlen($suche) < 2) { echo json_encode([]); exit; }

$service = new KundenService();
$alle    = $service->getAll($suche);

$result = [];
foreach ($alle as $k) {
    if ($k['ist_laufkunde']) continue;
    $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? ''));
    if ($k['ist_firma'] && !empty($k['firmenname'])) $name = $k['firmenname'];

    $adressen        = $service->getAdressen($k['id']);
    $lieferAdresse   = null;
    $rechnungsAdresse = null;
    foreach ($adressen as $a) {
        if ($a['adresstyp'] === 'lieferung'  && $a['ist_standard'] && !$lieferAdresse)   $lieferAdresse   = $a;
        if ($a['adresstyp'] === 'rechnung'   && $a['ist_standard'] && !$rechnungsAdresse) $rechnungsAdresse = $a;
    }
    // Fallback: erste Adresse des Typs wenn kein Standard gesetzt
    if (!$lieferAdresse)    foreach ($adressen as $a) { if ($a['adresstyp'] === 'lieferung')  { $lieferAdresse   = $a; break; } }
    if (!$rechnungsAdresse) foreach ($adressen as $a) { if ($a['adresstyp'] === 'rechnung')   { $rechnungsAdresse = $a; break; } }

    $adresseKompakt = fn($a) => $a ? [
        'vorname'    => $a['vorname']    ?? '',
        'nachname'   => $a['nachname']   ?? '',
        'firma'      => $a['firma']      ?? '',
        'strasse'    => $a['strasse']    ?? '',
        'hausnummer' => $a['hausnummer'] ?? '',
        'plz'        => $a['plz']        ?? '',
        'ort'        => $a['ort']        ?? '',
        'land'       => $a['land']       ?? 'AT',
        'zusatz'     => $a['zusatz']     ?? '',
    ] : null;

    $result[] = [
        'id'               => $k['id'],
        'name'             => $name ?: ('Kd. ' . $k['kundennummer']),
        'email'            => $k['email'] ?? '',
        'lieferadresse'    => $adresseKompakt($lieferAdresse),
        'rechnungsadresse' => $adresseKompakt($rechnungsAdresse),
    ];
    if (count($result) >= 15) break;
}

echo json_encode($result);
