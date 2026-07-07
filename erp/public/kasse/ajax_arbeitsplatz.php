<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/arbeitsplatz/ArbeitsplatzService.php';

header('Content-Type: application/json; charset=utf-8');

$aktion = $_POST['aktion'] ?? $_GET['aktion'] ?? '';
$svc    = new ArbeitsplatzService();
$sid    = session_id();

switch ($aktion) {

    // ── Zustand beim Öffnen von kasse/index.php ─────────────────────────────────
    // GET/POST: aktion, token (aus localStorage, kann leer sein)
    case 'status':
        $token = trim($_REQUEST['token'] ?? '');
        echo json_encode($svc->pruefeZustand($token !== '' ? $token : null, $sid));
        break;

    // ── Auswahl bestätigt ────────────────────────────────────────────────────────
    // POST: aktion, modus ('kasse'|'sonstiges'), kasse_id ODER typ+name
    case 'waehlen':
        $modus = $_POST['modus'] ?? '';
        echo json_encode($svc->waehle($modus, $_POST, $sid));
        break;

    // ── Kollision per Manager-PIN übernehmen ────────────────────────────────────
    // POST: aktion, arbeitsplatz_id, manager_pin, token (kann leer sein)
    case 'kollision_uebernehmen':
        $arbeitsplatzId = (int)($_POST['arbeitsplatz_id'] ?? 0);
        $pin            = $_POST['manager_pin'] ?? '';
        $token          = trim($_POST['token'] ?? '') ?: null;

        if (!$arbeitsplatzId) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Fehlende Parameter.']);
            break;
        }
        echo json_encode($svc->uebernehmeKollision($arbeitsplatzId, $pin, $sid, $token));
        break;

    default:
        echo json_encode(['erfolg' => false, 'fehler' => 'Unbekannte Aktion: ' . htmlspecialchars($aktion)]);
}
