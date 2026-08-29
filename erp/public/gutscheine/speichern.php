<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/gutscheine/GutscheinService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: neu.php');
    exit;
}

$daten = [
    'betrag'           => (float)($_POST['betrag'] ?? 0),
    'vorlage_id'        => $_POST['vorlage_id'] ?? null,
    'versandart'       => $_POST['versandart'] ?? 'selbst_ausdrucken',
    'empfaenger_name'  => trim($_POST['empfaenger_name'] ?? '') ?: null,
    'empfaenger_email' => trim($_POST['empfaenger_email'] ?? '') ?: null,
    'zustellung_am'    => trim($_POST['zustellung_am'] ?? '') ?: null,
    'grusstext'        => trim($_POST['grusstext'] ?? '') ?: null,
    'kanal_erstellt'   => 'manuell',
];

$service = new GutscheinService();
$ergebnis = $service->erstelleGutschein($daten, (int)$_SESSION['benutzer']['id']);

if (!$ergebnis['erfolg']) {
    $_SESSION['fehler']   = $ergebnis['fehler'];
    $_SESSION['formdata'] = $_POST;
    header('Location: neu.php');
    exit;
}

// Sofortversand, außer der Ersteller hat ein späteres Zustelldatum gewählt
// (dann übernimmt der gutschein_versand-Cronjob).
if (empty($daten['zustellung_am']) && $daten['versandart'] === 'versenden') {
    try {
        $service->versende((int)$ergebnis['id']);
    } catch (Throwable $e) {
        Logger::log('gutschein.versand_fehler', 'gutscheine', (int)$ergebnis['id'], [
            'fehler' => $e->getMessage(),
        ], (int)$_SESSION['benutzer']['id'], 'error');
    }
}

$_SESSION['erfolg'] = 'Gutschein ' . $ergebnis['code'] . ' wurde erstellt.';
header('Location: detail.php?id=' . $ergebnis['id']);
exit;
