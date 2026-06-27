<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
header('Content-Type: application/json');

$code    = trim($_GET['code']    ?? '');
$lagerId = (int)($_GET['lager_id'] ?? 1);

if (strlen($code) < 1) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Kein Code übergeben.']);
    exit;
}

$service = new KassenService();
$artikel = $service->findArtikelByCode($code, $lagerId);

if (!$artikel) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Artikel nicht gefunden: ' . $code]);
    exit;
}

echo json_encode(array_merge(['erfolg' => true], $artikel));
