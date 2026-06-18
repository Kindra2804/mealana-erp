<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/achsen/AchsenRepository.php';

header('Content-Type: application/json');

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$id       = (int)($body['id'] ?? 0);
$richtung = $body['richtung'] ?? '';
$parentId = isset($body['parent_id']) && $body['parent_id'] > 0 ? (int)$body['parent_id'] : null;

if (!$id || !in_array($richtung, ['hoch', 'runter'])) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Parameter']);
    exit;
}

$repo        = new AchsenRepository();
$geschwister = $repo->findByParentId($parentId);

// Normalisieren
foreach ($geschwister as $i => $a) {
    $repo->updateSortOrder((int)$a['id'], ($i + 1) * 10);
}

// Position der zu sortierenden Achse
$pos = null;
foreach ($geschwister as $i => $a) {
    if ((int)$a['id'] === $id) { $pos = $i; break; }
}

if ($pos === null) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Achse nicht gefunden']);
    exit;
}

$n = count($geschwister);
if ($richtung === 'hoch' && $pos > 0) {
    $repo->updateSortOrder($id, $pos * 10);
    $repo->updateSortOrder((int)$geschwister[$pos - 1]['id'], ($pos + 1) * 10);
} elseif ($richtung === 'runter' && $pos < $n - 1) {
    $repo->updateSortOrder($id, ($pos + 2) * 10);
    $repo->updateSortOrder((int)$geschwister[$pos + 1]['id'], ($pos + 1) * 10);
}

echo json_encode(['erfolg' => true]);
