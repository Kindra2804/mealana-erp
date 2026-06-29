<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';
header('Content-Type: application/json');

$artikelId = (int)($_GET['artikel_id'] ?? 0);
$lagerId   = (int)($_GET['lager_id'] ?? 1);

if (!$artikelId) { echo json_encode([]); exit; }

$db   = Database::getInstance();
$stmt = $db->prepare("
    SELECT id, charge, bestand, charge_status
    FROM lagerbestand
    WHERE artikel_id = :aid AND lager_id = :lid
      AND bestand > 0 AND charge IS NOT NULL
    ORDER BY charge_status = 'nachzutragen' DESC, erstellt_am ASC
");
$stmt->execute([':aid' => $artikelId, ':lid' => $lagerId]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
