<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/packplatz/RuecklagerungRepository.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ruecklagerungen.php'); exit;
}

$repo       = new RuecklagerungRepository();
$lagerSvc   = new LagerService();
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

$id      = (int)($_POST['id'] ?? 0);
$lagerId = (int)($_POST['lager_id'] ?? 0);
$zustand = $_POST['zustand'] ?? 'neu';
$charge  = trim($_POST['charge'] ?? '') ?: null;

$eintrag = $id ? $repo->findById($id) : null;
if (!$eintrag || $eintrag['status'] !== 'offen' || !$lagerId) {
    $_SESSION['fehler'] = 'Eintrag nicht gefunden oder bereits erledigt.';
    header('Location: ruecklagerungen.php'); exit;
}

// Serverseitige Chargenpflicht-Sperre — die Client-Prüfung im Modal allein reicht
// nicht (direkter POST würde sie umgehen), siehe Jackys Hinweis: "spätestens am
// Packplatz muss es eine Chargenzuordnung geben, sonst haben wir wieder Artikel
// in ungültigem Zustand". Übernommene Charge aus der Kasse zählt genauso wie eine
// hier neu eingetragene.
$finaleCharge = $charge ?: $eintrag['charge'];
if ($eintrag['charge_pflicht'] && !$finaleCharge) {
    $_SESSION['fehler'] = 'Dieser Artikel ist chargenpflichtig — bitte Charge eintragen, bevor eingebucht wird.';
    header('Location: ruecklagerungen.php'); exit;
}

$lagerSvc->wareneingang([
    'artikel_id'  => $eintrag['artikel_id'],
    'lager_id'    => $lagerId,
    'menge'       => $eintrag['menge'],
    'charge'      => $finaleCharge,
    'referenz'    => 'Rücklagerung Bon ' . $eintrag['bon_nr'],
    'notiz'       => 'Retoure aus Kasse — Zustand: ' . $zustand,
    'benutzer_id' => $benutzerId,
]);

$repo->markiereErledigt($id, $lagerId, $zustand, $benutzerId, $finaleCharge);

$_SESSION['erfolg'] = $eintrag['menge'] . '× ' . $eintrag['bezeichnung'] . ' eingebucht.';
header('Location: ruecklagerungen.php');
exit;
