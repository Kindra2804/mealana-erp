<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellDokumentService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/bestellungen/liste.php');
    exit;
}

$bestellungId = (int)($_POST['bestellung_id'] ?? 0);
if (!$bestellungId) {
    $_SESSION['fehler'] = ['Ungültige Anfrage.'];
    header('Location: ' . BASE_PATH . '/bestellungen/liste.php');
    exit;
}

$service    = new BestellDokumentService();
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);
$ergebnis   = $service->erstellePdf($bestellungId, $benutzerId);

if ($ergebnis['erfolg']) {
    $_SESSION['erfolg'] = 'PDF wurde erstellt.';
    header('Location: ' . BASE_PATH . '/bestellungen/detail.php?id=' . $bestellungId . '&neu=' . urlencode($ergebnis['dateiname']));
} else {
    $_SESSION['fehler'] = [$ergebnis['fehler'] ?? 'Fehler beim Erstellen des Dokuments.'];
    header('Location: ' . BASE_PATH . '/bestellungen/detail.php?id=' . $bestellungId);
}
exit;
