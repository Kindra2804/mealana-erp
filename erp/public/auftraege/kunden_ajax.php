<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';

header('Content-Type: application/json; charset=utf-8');

$suche = trim($_GET['q'] ?? '');
if (strlen($suche) < 2) { echo json_encode([]); exit; }

$service = new KundenService();
$alle    = $service->getAll($suche);   // filtert PHP-seitig nach entschlüsselten Feldern

$result = [];
foreach ($alle as $k) {
    if ($k['ist_laufkunde']) continue;
    $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? ''));
    if ($k['ist_firma'] && !empty($k['firmenname'])) $name = $k['firmenname'];
    $result[] = [
        'id'    => $k['id'],
        'name'  => $name ?: ('Kd. ' . $k['kundennummer']),
        'email' => $k['email'] ?? '',
    ];
    if (count($result) >= 15) break;
}

echo json_encode($result);
