<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

header('Content-Type: application/json');

$ean = trim($_GET['ean'] ?? '');

if (strlen($ean) < 8) {
    echo json_encode(['gefunden' => false]);
    exit;
}

$db = Database::getInstance();
$stmt = $db->prepare("
    SELECT a.id, a.artikelnummer, a.name
    FROM artikel_codes ac
    JOIN artikel a ON a.id = ac.artikel_id
    WHERE ac.typ = 'GTIN13' AND ac.code = :ean
    LIMIT 1
");
$stmt->execute(['ean' => $ean]);
$artikel = $stmt->fetch();

if ($artikel) {
    echo json_encode([
        'gefunden'     => true,
        'id'           => $artikel['id'],
        'artikelnummer' => $artikel['artikelnummer'],
        'name'         => $artikel['name'],
    ]);
} else {
    echo json_encode(['gefunden' => false]);
}
