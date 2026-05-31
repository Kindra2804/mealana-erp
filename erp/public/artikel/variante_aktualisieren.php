<?php
session_start();
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: variante_bearbeiten.php');
    exit;
}

$variantenData = $_POST;

// Leere Strings zu NULL konvertieren
foreach ($variantenData as $key => $value) {
    if ($value === '') {
        $variantenData[$key] = null;
    }
}

$service = new ArtikelService();
$result = $service->varianteUpdate($variantenData);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Variante wurde aktualisiert!';
    header('Location: detail.php?id=' . (int)$variantenData['artikel_id']);
    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $variantenData;
    header('Location: variante_bearbeiten.php?id=' . (int)$variantenData['id']);
    exit;
}
