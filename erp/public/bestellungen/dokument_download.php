<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellDokumentService.php';

$bestellungId = (int)($_GET['bestellung_id'] ?? 0);
$dateiname    = basename($_GET['datei'] ?? '');  // basename() verhindert Path-Traversal

if (!$bestellungId || !$dateiname) {
    http_response_code(400);
    exit('Ungültige Anfrage.');
}

$service   = new BestellDokumentService();
$dateipfad = $service->getDateipfad($bestellungId, $dateiname);

if (!file_exists($dateipfad)) {
    http_response_code(404);
    exit('Datei nicht gefunden.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $dateiname . '"');
header('Content-Length: ' . filesize($dateipfad));
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($dateipfad);
exit;
