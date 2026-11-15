<?php
// DEP-Export (RKSV §7) einer Kasse: holt das komplette Datenerfassungsprotokoll vom BFR
// (GET /control/export) und bietet es als JSON-Download an. Aufruf aus Kasse → RKSV.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/BfrService.php';

$kasseId = (int)($_GET['kasse_id'] ?? 0);
if ($kasseId < 1) {
    header('Location: ' . BASE_PATH . '/kasse/nacherfassung.php');
    exit;
}

set_time_limit(240);
$ergebnis = (new BfrService())->holeDepExport($kasseId, (int)$_SESSION['benutzer']['id']);

if (!$ergebnis['erfolg']) {
    $_SESSION['fehler']        = $ergebnis['fehler'];
    $_SESSION['dep_lokal_url'] = $ergebnis['lokal_url'];
    header('Location: ' . BASE_PATH . '/kasse/nacherfassung.php');
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $ergebnis['dateiname'] . '"');
header('Content-Length: ' . strlen($ergebnis['daten']));
header('Cache-Control: no-store');
echo $ergebnis['daten'];
