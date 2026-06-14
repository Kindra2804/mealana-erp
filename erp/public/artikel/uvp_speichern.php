<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Methode']);
    exit;
}

$artikelId = (int) ($_POST['artikel_id'] ?? 0);
$uvp       = trim($_POST['uvp'] ?? '');

if ($artikelId <= 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Artikel fehlt']);
    exit;
}

$db   = Database::getInstance();
$stmt = $db->prepare("UPDATE artikel SET uvp = :uvp WHERE id = :id");
$stmt->execute(['uvp' => $uvp !== '' ? (float)$uvp : null, 'id' => $artikelId]);

echo json_encode(['erfolg' => true]);
