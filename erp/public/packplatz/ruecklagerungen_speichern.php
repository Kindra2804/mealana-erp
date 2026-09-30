<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/packplatz/RuecklagerungRepository.php';
require_once __DIR__ . '/../../src/modules/packplatz/RetourService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ruecklagerungen.php'); exit;
}

$repo       = new RuecklagerungRepository();
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

$id      = (int)($_POST['id'] ?? 0);
$lagerId = (int)($_POST['lager_id'] ?? 0);
$zustand = $_POST['zustand'] ?? 'neu';
$charge  = trim($_POST['charge'] ?? '') ?: null;

if (!in_array($zustand, ['neu', 'retour', 'gebraucht', 'beschaedigt', 'defekt'], true)) {
    $zustand = 'neu';
}

$eintrag = $id ? $repo->findById($id) : null;
if (!$eintrag || $eintrag['status'] !== 'offen' || !$lagerId) {
    $_SESSION['fehler'] = 'Eintrag nicht gefunden oder bereits erledigt.';
    header('Location: ruecklagerungen.php'); exit;
}

// Serverseitige Chargenpflicht-Sperre — die Client-Prüfung im Modal allein reicht
// nicht (direkter POST würde sie umgehen), siehe Jackys Hinweis: "spätestens am
// Packplatz muss es eine Chargenzuordnung geben, sonst haben wir wieder Artikel
// in ungültigem Zustand". Übernommene Charge aus Kasse/Gutschrift zählt genauso wie
// eine hier neu eingetragene. Defekte Ware wird sofort als Schwund ausgebucht -> Charge optional (oft nicht mehr lesbar).
$finaleCharge = $charge ?: $eintrag['charge'];
if ($eintrag['charge_pflicht'] && !$finaleCharge && $zustand !== 'defekt') {
    $_SESSION['fehler'] = 'Dieser Artikel ist chargenpflichtig — bitte Charge eintragen, bevor eingebucht wird.';
    header('Location: ruecklagerungen.php'); exit;
}

$herkunft = $eintrag['quelle'] === 'gutschrift'
    ? 'Gutschrift ' . $eintrag['gutschrift_nr']
    : 'Bon ' . $eintrag['bon_nr'];

try {
    $r = (new RetourService())->einbuchen([
        'artikel_id'  => (int)$eintrag['artikel_id'],
        'lager_id'    => $lagerId,
        'menge'       => (int)$eintrag['menge'],
        'zustand'     => $zustand,
        'charge'      => $finaleCharge,
        'referenz'    => 'Rücklagerung ' . $herkunft,
        'notiz'       => 'Retoure' . ($eintrag['auftrag_nr'] ? ' ' . $eintrag['auftrag_nr'] : ''),
        'benutzer_id' => $benutzerId,
    ]);
} catch (Throwable $e) {
    $r = ['erfolg' => false, 'fehler' => $e->getMessage()];
}
if (!$r['erfolg']) {
    $_SESSION['fehler'] = 'Einbuchen fehlgeschlagen: ' . ($r['fehler'] ?? 'unbekannter Fehler');
    header('Location: ruecklagerungen.php'); exit;
}

$repo->markiereErledigt($id, $lagerId, $zustand, $benutzerId, $finaleCharge, $r['artikel_id']);

$_SESSION['erfolg'] = $eintrag['bezeichnung'] . ': ' . $r['text'];
header('Location: ruecklagerungen.php');
exit;
