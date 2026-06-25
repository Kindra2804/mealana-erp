<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/dokumente/DokumentService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

$auftragId = (int)($_POST['auftrag_id'] ?? 0);
$typ       = trim($_POST['typ'] ?? '');

if (!$auftragId || !$typ) {
    $_SESSION['fehler'] = ['Ungültige Anfrage.'];
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

$service    = new DokumentService();
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

$ergebnis = match($typ) {
    'rechnung'             => $service->erstelleRechnung($auftragId, $benutzerId),
    'auftragsbestaetigung' => $service->erstelleAuftragsbestaetigung($auftragId, $benutzerId),
    'lieferschein'         => $service->erstelleLieferschein($auftragId, $benutzerId),
    'abholzettel'          => $service->erstelleAbholzettel($auftragId, $benutzerId),
    default                => ['erfolg' => false, 'fehler' => 'Unbekannter Dokumenttyp.'],
};

if ($ergebnis['erfolg']) {
    $_SESSION['erfolg'] = 'Dokument wurde erstellt.';
    // Direkt zum Download weiterleiten
    header('Location: /mealana/auftraege/dokument_download.php?auftrag_id=' . $auftragId
        . '&datei=' . urlencode($ergebnis['dateiname']));
} else {
    $_SESSION['fehler'] = [$ergebnis['fehler'] ?? 'Fehler beim Erstellen des Dokuments.'];
    header('Location: /mealana/auftraege/detail.php?id=' . $auftragId);
}
exit;
