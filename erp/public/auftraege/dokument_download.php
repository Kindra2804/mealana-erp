<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/dokumente/DokumentService.php';

$auftragId = (int)($_GET['auftrag_id'] ?? 0);
$dateiname = basename($_GET['datei'] ?? '');  // basename() verhindert Path-Traversal

if (!$auftragId || !$dateiname) {
    http_response_code(400);
    exit('Ungültige Anfrage.');
}

$service   = new DokumentService();
$dateipfad = $service->getDateipfad($auftragId, $dateiname);

// Rechnung: immer mit dem AKTUELLEN Zahlungsstand ausgeben (Zahlungsinfo: Überweisung,
// Zahlbeleg an der Kasse, offener Betrag). Nummer/Datum/Inhalt bleiben gleich, das
// Original-PDF bleibt unverändert gespeichert (Klicktest 2026-10-07).
$re = Database::getInstance()->prepare("SELECT id, rechnung_nr FROM rechnungen WHERE auftrag_id = ? AND dateiname = ?");
$re->execute([$auftragId, $dateiname]);
if ($rechnung = $re->fetch(PDO::FETCH_ASSOC)) {
    try {
        $dateipfad = $service->renderRechnung((int)$rechnung['id'], 'Nachdruck_' . $rechnung['rechnung_nr'] . '.pdf');
    } catch (Throwable $e) {
        error_log('[Rechnung Nachdruck] ' . $e->getMessage()); // dann eben das Original
    }
}

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
