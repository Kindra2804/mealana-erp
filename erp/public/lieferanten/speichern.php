<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: neu.php');
    exit;
}

$data = $_POST;

$lieferantenData = array_intersect_key($data, array_flip([
    'name', 'firma', 'firmenzusatz', 'land', 'strasse', 'plz', 'ort', 'kundennummer',
    'ustid', 'steuerregel', 'waehrung',
    'website', 'email', 'telefon',
    'zahlungsziel_tage', 'skonto_prozent', 'skonto_tage',
    'mindestbestellwert', 'standard_lieferkosten', 'lieferzeit_tage', 'lieferbedingung',
    'iban', 'bic', 'bank_name', 'kontoinhaber',
    'interne_notizen', 'aktiv',
]));

// Leere Strings zu NULL konvertieren
foreach ($lieferantenData as $key => $value) {
    if ($value === '') {
        $lieferantenData[$key] = null;
    }
}

$vertreterRows = $data['vertreter'] ?? [];

$service = new LieferantenService();
$result = $service->save($lieferantenData);

if ($result['erfolg']) {
    // Vertreter-Zeilen aus dem Anlageformular mitspeichern.
    // Leere Zeilen (kein Nachname eingetragen) werden übersprungen.
    foreach ($vertreterRows as $row) {
        if (trim($row['nachname'] ?? '') === '') {
            continue;
        }
        $row['lieferant_id'] = $result['id'];
        $service->saveVertreter($row);
    }

    $_SESSION['erfolg'] = 'Lieferant wurde gespeichert!';
    header('Location: detail.php?id=' . $result['id']);
    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $lieferantenData;
    $_SESSION['formdata']['vertreter'] = $vertreterRows;
    header('Location: neu.php');
    exit;
}
