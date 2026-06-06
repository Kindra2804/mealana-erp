<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: wareneingang.php');
    exit;
}

$data = $_POST;

// Leere Strings zu NULL konvertieren
foreach ($data as $key => $value) {
    if ($value === '') {
        $data[$key] = null;
    }
}

$data['benutzer_id'] = $_SESSION['benutzer']['id'] ?? null;

$service = new LagerService();
$result = $service->wareneingang($data);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Wareneingang wurde verarbeitet!';
    header('Location: wareneingang.php');
    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $data;
    header('Location: wareneingang.php');
    exit;
}
