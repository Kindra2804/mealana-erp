<?php
// Datenexport Kasse für die Betriebsprüfung (§ 131 BAO): ZIP mit TAB-CSV je Kassentabelle
// (+ optional DEP vom BFR). Formular in Kasse → RKSV.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/PruefExportService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/kasse/nacherfassung.php');
    exit;
}

set_time_limit(600);
$kasseId = (int)($_POST['kasse_id'] ?? 0) ?: null;
$ergebnis = (new PruefExportService())->erstelleZip(
    (string)($_POST['von'] ?? ''),
    (string)($_POST['bis'] ?? ''),
    $kasseId,
    !empty($_POST['mit_dep']),
    (int)$_SESSION['benutzer']['id']
);

if (!$ergebnis['erfolg']) {
    $_SESSION['fehler'] = $ergebnis['fehler'];
    header('Location: ' . BASE_PATH . '/kasse/nacherfassung.php');
    exit;
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $ergebnis['dateiname'] . '"');
header('Content-Length: ' . filesize($ergebnis['pfad']));
header('Cache-Control: no-store');
readfile($ergebnis['pfad']);
@unlink($ergebnis['pfad']);
