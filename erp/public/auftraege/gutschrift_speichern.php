<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/dokumente/DokumentService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

$auftragId  = (int)($_POST['auftrag_id'] ?? 0);
$rechnungId = (int)($_POST['rechnung_id'] ?? 0);
$gsArt      = trim($_POST['gs_art'] ?? 'teilgutschrift');
$grund      = trim($_POST['grund'] ?? '');
$lagerRueck = !empty($_POST['lager_rueckbuchen']);
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

if (!$auftragId || !$rechnungId || !$benutzerId) {
    $_SESSION['fehler'] = ['Ungültige Anfrage.'];
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

// Positionen aus POST aufbereiten (bei Teilgutschrift)
$positionen = [];
if ($gsArt === 'teilgutschrift' && !empty($_POST['positionen'])) {
    foreach ($_POST['positionen'] as $item) {
        if (empty($item['aktiv'])) continue;
        $menge = max(1, (int)($item['menge'] ?? 1));
        $positionen[] = [
            'pos_id'           => (int)($item['pos_id'] ?? 0),
            'menge'            => $menge,
            'steuer_prozent'   => (float)($item['steuer_prozent'] ?? 20),
            'einzelpreis_netto'=> (float)($item['einzelpreis_netto'] ?? 0),
            'artikel_id'       => (int)($item['artikel_id'] ?? 0),
        ];
    }
    if (empty($positionen)) {
        $_SESSION['fehler']   = ['Bitte mindestens eine Position auswählen.'];
        $_SESSION['formdata'] = $_POST;
        header('Location: /mealana/auftraege/gutschrift_erstellen.php?auftrag_id=' . $auftragId);
        exit;
    }
}

$service  = new DokumentService();
$ergebnis = $service->erstelleGutschrift(
    $auftragId, $rechnungId, $benutzerId,
    $gsArt, $positionen, $grund, $lagerRueck
);

if ($ergebnis['erfolg']) {
    $_SESSION['erfolg'] = 'Gutschrift ' . $ergebnis['gs_nr'] . ' wurde erstellt.';
    header('Location: /mealana/auftraege/dokument_download.php?auftrag_id=' . $auftragId
        . '&datei=' . urlencode($ergebnis['dateiname']));
} else {
    $_SESSION['fehler']   = [$ergebnis['fehler'] ?? 'Fehler beim Erstellen der Gutschrift.'];
    $_SESSION['formdata'] = $_POST;
    header('Location: /mealana/auftraege/gutschrift_erstellen.php?auftrag_id=' . $auftragId);
}
exit;
