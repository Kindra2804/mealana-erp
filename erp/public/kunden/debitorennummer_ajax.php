<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$id     = (int)($_POST['id'] ?? 0);
$nummer = (string)($_POST['debitorennummer'] ?? '');

if (!$id) {
    echo json_encode(['erfolg' => false, 'fehler' => 'ID fehlt']);
    exit;
}

echo json_encode((new KundenService())->debitorennummerAendern($id, $nummer));
