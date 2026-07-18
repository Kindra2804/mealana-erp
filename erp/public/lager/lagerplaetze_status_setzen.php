<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => ['Nur POST erlaubt']]);
    exit;
}

$id    = (int)($_POST['id']    ?? 0);
$aktiv = (int)($_POST['aktiv'] ?? 0);

if (!$id) {
    echo json_encode(['erfolg' => false, 'fehler' => ['ID fehlt']]);
    exit;
}

echo json_encode((new LagerService())->setLagerplatzAktiv($id, $aktiv));
