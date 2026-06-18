<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/achsen/AchsenService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => ['Nur POST erlaubt']]);
    exit;
}

$abhaengigVon = (int)($_POST['abhaengig_von_achse_id'] ?? 0) ?: null;

$data = [
    'name'                   => trim($_POST['name'] ?? ''),
    'code'                   => strtolower(str_replace(' ', '_', trim($_POST['code'] ?? ''))),
    'darstellungsform'       => $_POST['darstellungsform'] ?? 'dropdown',
    'abhaengig_von_achse_id' => $abhaengigVon,
    'sort_order'             => (int)($_POST['sort_order'] ?? 0),
];

$service = new AchsenService();
echo json_encode($service->save($data));
