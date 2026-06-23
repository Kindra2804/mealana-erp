<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';
header('Content-Type: application/json');

$alle        = !empty($_GET['alle']);
$lieferantId = (int)($_GET['lieferant_id'] ?? 0);
$q           = (string)($_GET['q'] ?? '');

$service = new BestellungService();

if ($alle) {
    if (strlen($q) < 2) { echo json_encode([]); exit; }
    echo json_encode($service->getAlleArtikelFuerSuche($q));
} else {
    if (!$lieferantId) { echo json_encode([]); exit; }
    echo json_encode($service->getArtikelFuerLieferant($lieferantId, $q));
}
