<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../src/core/Database.php';

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$db = Database::getInstance();
$stmt = $db->prepare("
    SELECT 
        v.id,
        v.artikelnummer,
        v.gtin,
        v.farbe_name,
        a.name AS artikel_name
    FROM artikel_varianten v
    INNER JOIN artikel a ON v.artikel_id = a.id
    WHERE v.aktiv = 1
    AND (
        v.artikelnummer LIKE :q
        OR v.gtin = :exact
        OR v.farbe_name LIKE :q
        OR a.name LIKE :q
    )
    ORDER BY v.artikelnummer ASC
    LIMIT 10
");

$stmt->execute([
    'q'     => '%' . $q . '%',
    'exact' => $q
]);

echo json_encode($stmt->fetchAll());
