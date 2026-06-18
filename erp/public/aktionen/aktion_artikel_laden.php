<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/aktionen/AktionenService.php';

header('Content-Type: application/json');

$aktionId   = (int)($_GET['aktion_id'] ?? 0);
$kategorieId = (int)($_GET['kategorie_id'] ?? 0);
$kgId       = (int)($_GET['kg_id'] ?? 1);

if (!$aktionId || !$kategorieId) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Pflichtparameter fehlen']);
    exit;
}

$service = new AktionenService();
$artikel = $service->getArtikelMitSubAchsenUndPreisen($aktionId, $kategorieId, $kgId);

echo json_encode(['erfolg' => true, 'artikel' => $artikel]);
