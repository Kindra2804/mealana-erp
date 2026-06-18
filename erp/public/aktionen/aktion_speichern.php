<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/aktionen/AktionenService.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$service      = new AktionenService();
$modus        = $_POST['modus'] ?? '';
$name         = trim($_POST['name'] ?? '');
$beschreibung = trim($_POST['beschreibung'] ?? '') ?: null;

if ($modus === 'neu') {
    $result = $service->create($name, $beschreibung);
    if ($result['erfolg']) {
        header('Location: /mealana/aktionen/bearbeiten.php?id=' . $result['id']);
        exit;
    }
    // Bei Fehler: zurück mit Fehlermeldung (einfach als GET-Param)
    header('Location: /mealana/aktionen/bearbeiten.php?fehler=' . urlencode($result['fehler']));
    exit;
}

if ($modus === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    echo json_encode($service->update($id, $name, $beschreibung));
    exit;
}

echo json_encode(['erfolg' => false, 'fehler' => 'Unbekannter Modus']);
