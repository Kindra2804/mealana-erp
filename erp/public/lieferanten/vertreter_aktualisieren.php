<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: vertreter_bearbeiten.php');
    exit;
}

$data = $_POST;

// Leere Strings zu NULL konvertieren
foreach ($data as $key => $value) {
    if ($value === '') {
        $data[$key] = null;
    }
}

// vertreter_aktualisieren.php muss diese Felder herausfiltern:
$vertreterData = array_intersect_key($data, array_flip([
    'id',
    'lieferant_id',
    'vorname',
    'nachname',
    'telefon',
    'email',
    'mobil',
    'notizen',
    'aktiv'
]));

$service = new LieferantenService();
$result = $service->updateVertreter($vertreterData);
if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Vertreter wurde aktualisiert!';
    header('Location: detail.php?id=' . $vertreterData['lieferant_id']);
    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $data;
    header('Location: vertreter_bearbeiten.php?id=' . $vertreterData['id']);
    exit;
}
