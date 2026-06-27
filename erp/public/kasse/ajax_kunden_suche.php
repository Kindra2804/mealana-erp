<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';
header('Content-Type: application/json; charset=utf-8');

$suche = trim($_GET['suche'] ?? '');
if (strlen($suche) < 2) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Mindestens 2 Zeichen.']);
    exit;
}

$svc     = new KundenService();
$kunden  = $svc->getAll($suche, 'aktiv');
$kunden  = array_slice($kunden, 0, 20);

$result = array_map(function($k) {
    $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? ''));
    if ($k['ist_firma'] && !empty($k['firmenname'])) {
        $name = $k['firmenname'] . ($name ? ' (' . $name . ')' : '');
    }
    return [
        'id'           => $k['id'],
        'kundennummer' => $k['kundennummer'],
        'name'         => $name ?: '—',
        'email'        => $k['email'] ?? '',
        'ist_firma'    => (bool)$k['ist_firma'],
        'kundengruppe' => $k['kundengruppe'] ?? '',
    ];
}, $kunden);

echo json_encode(['erfolg' => true, 'kunden' => $result, 'anzahl' => count($result)]);
