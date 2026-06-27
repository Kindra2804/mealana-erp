<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: neu.php');
    exit;
}

$data = $_POST;

$lieferantenData = array_intersect_key($data, array_flip([
    'name', 'land', 'strasse', 'plz', 'ort', 'kundennummer', 'waehrung',
    'website', 'email', 'telefon',
    'zahlungsziel_tage', 'skonto_prozent', 'skonto_tage',
    'mindestbestellwert', 'lieferzeit_tage', 'lieferbedingung',
    'interne_notizen', 'aktiv',
]));

// Leere Strings zu NULL konvertieren
foreach ($lieferantenData as $key => $value) {
    if ($value === '') {
        $lieferantenData[$key] = null;
    }
}

$service = new LieferantenService();
$result = $service->save($lieferantenData);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Lieferant wurde gespeichert!';
    header('Location: detail.php?id=' . $result['id']);
    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $lieferantenData;
    header('Location: neu.php');
    exit;
}
