<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';
header('Content-Type: application/json');

$lieferantId = (int)($_GET['lieferant_id'] ?? 0);
if (!$lieferantId) {
    echo json_encode([]);
    exit;
}

$q = (string)($_GET['q'] ?? '');

$service = new BestellungService();
echo json_encode($service->getArtikelFuerLieferant($lieferantId, $q));
