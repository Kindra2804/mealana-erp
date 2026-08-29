<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

header('Content-Type: application/json');

$bonId = (int)($_GET['bon_id'] ?? 0);
if ($bonId <= 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Keine Bon-ID.']);
    exit;
}

$db = Database::getInstance();
$stmt = $db->prepare("
    SELECT g.id, g.code, g.betrag
    FROM gutschein_transaktionen t
    JOIN gutscheine g ON g.id = t.gutschein_id
    WHERE t.kassen_bon_id = :bon_id
    ORDER BY t.id DESC
    LIMIT 1
");
$stmt->execute(['bon_id' => $bonId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Kein Gutschein zu diesem Bon gefunden.']);
    exit;
}

echo json_encode(['erfolg' => true, 'id' => $row['id'], 'code' => $row['code'], 'betrag' => $row['betrag']]);
