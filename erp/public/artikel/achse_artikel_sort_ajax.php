<?php
/**
 * Sortiert die Achsen-Reihenfolge NUR für einen einzelnen Artikel (artikel_achsen.sort_order),
 * unabhängig von der globalen Achsen-Katalog-Reihenfolge (siehe achse_sort_tree_ajax.php, das die
 * globale varianten_achsen.sort_order ändert). Gleiches Normalisieren+Tauschen-Muster wie dort.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/varianten/VariantenRepository.php';

header('Content-Type: application/json');

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$artikelId = (int)($body['artikel_id'] ?? 0);
$achseId   = (int)($body['achse_id'] ?? 0);
$richtung  = $body['richtung'] ?? '';

if (!$artikelId || !$achseId || !in_array($richtung, ['hoch', 'runter'], true)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Parameter']);
    exit;
}

$repo    = new VariantenRepository();
$achsen  = $repo->findAchsenByArtikelId($artikelId);

// Normalisieren
foreach ($achsen as $i => $a) {
    $repo->updateAchseSortOrder($artikelId, (int)$a['achse_id'], ($i + 1) * 10);
}

$pos = null;
foreach ($achsen as $i => $a) {
    if ((int)$a['achse_id'] === $achseId) { $pos = $i; break; }
}

if ($pos === null) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Achse ist diesem Artikel nicht zugewiesen']);
    exit;
}

$n = count($achsen);
if ($richtung === 'hoch' && $pos > 0) {
    $repo->updateAchseSortOrder($artikelId, $achseId, $pos * 10);
    $repo->updateAchseSortOrder($artikelId, (int)$achsen[$pos - 1]['achse_id'], ($pos + 1) * 10);
} elseif ($richtung === 'runter' && $pos < $n - 1) {
    $repo->updateAchseSortOrder($artikelId, $achseId, ($pos + 2) * 10);
    $repo->updateAchseSortOrder($artikelId, (int)$achsen[$pos + 1]['achse_id'], ($pos + 1) * 10);
}

echo json_encode(['erfolg' => true]);
