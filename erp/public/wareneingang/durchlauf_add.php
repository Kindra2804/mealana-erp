<?php
require_once __DIR__ . '/../includes/auth_check.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false]);
    exit;
}

$artikelId = (int)($_POST['artikel_id'] ?? 0);
$menge     = max(1, (int)($_POST['menge'] ?? 1));
$name      = trim($_POST['name'] ?? '');
$ean       = trim($_POST['ean'] ?? '');

if (!$artikelId) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Artikel fehlt']);
    exit;
}

if (!isset($_SESSION['we_durchlauf'])) {
    $_SESSION['we_durchlauf'] = [];
}

// Artikel schon in der Liste → Menge addieren statt doppelt eintragen
$gefunden = false;
foreach ($_SESSION['we_durchlauf'] as &$item) {
    if ($item['artikel_id'] === $artikelId) {
        $item['menge'] += $menge;
        $gefunden = true;
        break;
    }
}

if (!$gefunden) {
    $_SESSION['we_durchlauf'][] = [
        'artikel_id' => $artikelId,
        'menge'      => $menge,
        'name'       => $name,
        'ean'        => $ean,
    ];
}

echo json_encode([
    'erfolg' => true,
    'anzahl' => count($_SESSION['we_durchlauf']),
]);
