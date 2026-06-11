<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/achsen/AchsenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bearbeiten.php');
    exit;
}

$data = $_POST;

// aktualisieren.php muss diese Felder herausfiltern:
$achsenData = array_intersect_key($data, array_flip([
    'id',
    'name',
    'code',
    'darstellungsform',
    'sort_order'
]));

if (!empty($achsenData['code'])) {
    $achsenData['code'] = strtolower(str_replace(' ', '_', $achsenData['code']));
}

$service = new AchsenService();
$result = $service->update($achsenData);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Achse wurde aktualisiert!';
    header('Location: liste.php');
    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $achsenData;
    header('Location: bearbeiten.php?id=' . $achsenData['id']);
    exit;
}
