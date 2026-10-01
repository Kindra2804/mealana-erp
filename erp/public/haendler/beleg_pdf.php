<?php
/** Lieferschein (HL), Rücknahmeschein (HR) bzw. Schwund-Protokoll (HS) eines Händlers als PDF. */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/haendler/HaendlerService.php';
require_once __DIR__ . '/../../src/modules/dokumente/PdfGenerator.php';

$daten = (new HaendlerService())->belegDokument((int)($_GET['id'] ?? 0));
if (!$daten) { http_response_code(404); exit('Beleg nicht gefunden.'); }

$pfad = __DIR__ . '/../../storage/haendler_belege/' . preg_replace('/[^A-Za-z0-9\-]/', '', $daten['beleg']['nummer']) . '.pdf';
if (!file_exists($pfad)) {
    (new PdfGenerator())->generiere('haendler/beleg.html.twig', $daten, $pfad);
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $daten['beleg']['nummer'] . '.pdf"');
readfile($pfad);
