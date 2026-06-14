<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$artikelId = (int) ($_POST['artikel_id'] ?? 0);
$lagerId   = (int) ($_POST['lager_id']   ?? 0);
$menge     = (float) ($_POST['menge']    ?? 0);
$charge    = trim($_POST['charge'] ?? '') ?: null;
$notiz     = trim($_POST['notiz']  ?? '') ?: null;

$redirect = 'detail.php?id=' . $artikelId . '&tab=lager';

if ($artikelId <= 0 || $lagerId <= 0 || $menge <= 0) {
    header('Location: ' . $redirect . '&we_fehler=' . urlencode('Ungültige Eingabe'));
    exit;
}

$service  = new LagerService();
$ergebnis = $service->wareneingang([
    'artikel_id'  => $artikelId,
    'lager_id'    => $lagerId,
    'menge'       => $menge,
    'charge'      => $charge,
    'notiz'       => $notiz,
    'benutzer_id' => $_SESSION['benutzer']['id'] ?? null,
]);

if (!$ergebnis['erfolg']) {
    $fehlerText = implode(', ', $ergebnis['fehler'] ?? ['Unbekannter Fehler']);
    header('Location: ' . $redirect . '&we_fehler=' . urlencode($fehlerText));
    exit;
}

header('Location: ' . $redirect);
exit;
