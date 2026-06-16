<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/KategorieRepository.php';

header('Content-Type: application/json');

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$id       = (int)($body['id'] ?? 0);
$richtung = $body['richtung'] ?? '';

if (!$id || !in_array($richtung, ['hoch', 'runter'])) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Parameter']);
    exit;
}

$repo    = new KategorieRepository();
$aktuelle = $repo->findById($id);
if (!$aktuelle) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Kategorie nicht gefunden']);
    exit;
}

$parentId    = isset($aktuelle['parent_id']) && $aktuelle['parent_id'] !== '' ? (int)$aktuelle['parent_id'] : null;
$geschwister = $repo->getSiblingsWithSort($parentId);

// Normalize: assign sequential values 10, 20, 30...
foreach ($geschwister as $i => $g) {
    $repo->updateSortierung((int)$g['id'], ($i + 1) * 10);
}

// Find position of current item in the ordered list
$pos = null;
foreach ($geschwister as $i => $g) {
    if ((int)$g['id'] === $id) { $pos = $i; break; }
}

if ($pos === null) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Position nicht gefunden']);
    exit;
}

$n = count($geschwister);
if ($richtung === 'hoch' && $pos > 0) {
    $repo->updateSortierung($id, $pos * 10);
    $repo->updateSortierung((int)$geschwister[$pos - 1]['id'], ($pos + 1) * 10);
} elseif ($richtung === 'runter' && $pos < $n - 1) {
    $repo->updateSortierung($id, ($pos + 2) * 10);
    $repo->updateSortierung((int)$geschwister[$pos + 1]['id'], ($pos + 1) * 10);
}

echo json_encode(['erfolg' => true]);
