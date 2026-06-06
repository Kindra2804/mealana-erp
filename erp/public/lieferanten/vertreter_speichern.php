<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: detail.php');
    exit;
}

$data = $_POST;

$vertreterData = array_intersect_key($data, array_flip([
    'lieferant_id',
    'vorname',
    'nachname',
    'telefon',
    'email',
    'mobil',
    'notizen',
    'aktiv'
]));

// Leere Strings zu NULL konvertieren
foreach ($vertreterData as $key => $value) {
    if ($value === '') {
        $vertreterData[$key] = null;
    }
}

$service = new LieferantenService();
$result = $service->saveVertreter($vertreterData);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Vertreter wurde gespeichert!';
    header('Location: detail.php?id=' . $vertreterData['lieferant_id']);
    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $vertreterData;
    header('Location: vertreter_neu.php?lieferant_id=' . $vertreterData['lieferant_id']);
    exit;
}
