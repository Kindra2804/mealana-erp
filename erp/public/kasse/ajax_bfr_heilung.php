<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/arbeitsplatz/ArbeitsplatzService.php';
require_once __DIR__ . '/../../src/modules/kasse/BfrService.php';

// Wird beim Laden von kasse/index.php per JS im Hintergrund aufgerufen (siehe
// kasse_arbeitsplatz.js, apHeileBfrUrl()). Meldet nur die lokal ausgelesene RN
// (Kassen-ID) — kein eigenes Popup, kein Fehlerfall den der Client behandeln
// müsste, siehe BfrService::heileUrlFuerKasse().
header('Content-Type: application/json; charset=utf-8');

$rn      = trim($_POST['rn'] ?? '');
$kasseId = (new ArbeitsplatzService())->aktuelleKasseId();

if ($rn !== '' && $kasseId !== null) {
    (new BfrService())->heileUrlFuerKasse($kasseId, $rn, $_SERVER['REMOTE_ADDR']);
}

echo json_encode(['erfolg' => true]);
