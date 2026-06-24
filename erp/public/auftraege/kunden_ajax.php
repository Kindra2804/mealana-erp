<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

header('Content-Type: application/json; charset=utf-8');

$suche = trim($_GET['q'] ?? '');
if (strlen($suche) < 2) { echo json_encode([]); exit; }

$db   = Database::getInstance();
$stmt = $db->prepare("
    SELECT id, name, email
    FROM kunden
    WHERE aktiv = 1 AND id != 1
      AND (name LIKE :suche OR email LIKE :suche)
    ORDER BY name
    LIMIT 15
");
$stmt->execute(['suche' => '%' . $suche . '%']);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
