<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bearbeiten.php');
    exit;
}

$data = $_POST;

// Leere Strings zu NULL konvertieren
foreach ($data as $key => $value) {
    if ($value === '') {
        $data[$key] = null;
    }
}

$service = new LieferantenService();
$result = $service->update($data);
if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Lieferant wurde aktualisiert!';
    header('Location: detail.php?id=' . $data['id']);
    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $data;
    header('Location: bearbeiten.php?id=' . $data['id']);
    exit;
}
