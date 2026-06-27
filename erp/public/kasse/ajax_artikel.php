<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
header('Content-Type: application/json');

$lagerId = (int)($_GET['lager_id'] ?? 1);
$service = new KassenService();

// ── Textsuche ──────────────────────────────────────────────────────────────
$suche = trim($_GET['suche'] ?? '');
if ($suche !== '') {
    if (strlen($suche) < 2) {
        echo json_encode(['erfolg' => false, 'fehler' => 'Mindestens 2 Zeichen eingeben.']);
        exit;
    }
    $ergebnisse = $service->sucheArtikel($suche, $lagerId, 25);
    echo json_encode(['erfolg' => true, 'ergebnisse' => $ergebnisse, 'anzahl' => count($ergebnisse)]);
    exit;
}

// ── EAN / Artikelnummer Lookup ────────────────────────────────────────────
$code = trim($_GET['code'] ?? '');
if (strlen($code) < 1) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Kein Code übergeben.']);
    exit;
}

$artikel = $service->findArtikelByCode($code, $lagerId);

if (!$artikel) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Artikel nicht gefunden: ' . $code]);
    exit;
}

echo json_encode(array_merge(['erfolg' => true], $artikel));
