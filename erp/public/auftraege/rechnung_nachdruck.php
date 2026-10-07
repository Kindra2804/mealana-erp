<?php
/**
 * Nachdruck einer (Teil-)Rechnung mit der AKTUELLEN Zahlungsinfo (z.B. "bar an Kasse 1 vom
 * 05.10.: 20,00 (Zahlbeleg K1-...)"). Rechnungsinhalt, Nummer und Datum bleiben unverändert
 * (eingefrorene rechnung_positionen); das Original-PDF wird nicht überschrieben.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/dokumente/DokumentService.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = Database::getInstance()->prepare("SELECT auftrag_id, rechnung_nr FROM rechnungen WHERE id = ?");
$stmt->execute([$id]);
$rechnung = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rechnung) {
    http_response_code(404);
    exit('Rechnung nicht gefunden.');
}

$pfad = (new DokumentService())->renderRechnung($id, 'Nachdruck_' . $rechnung['rechnung_nr'] . '.pdf');

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $rechnung['rechnung_nr'] . '.pdf"');
readfile($pfad);
