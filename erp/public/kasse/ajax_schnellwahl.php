<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
header('Content-Type: application/json');

$aktion  = $_GET['aktion'] ?? $_POST['aktion'] ?? 'laden';
$kasseId = (int)($_GET['kasse_id'] ?? $_POST['kasse_id'] ?? 1);
$svc     = new KassenService();

switch ($aktion) {

    case 'laden':
        echo json_encode(['erfolg' => true, 'schnellwahl' => $svc->getSchnellwahl($kasseId)]);
        break;

    case 'speichern':
        $slot      = (int)($_POST['slot']       ?? 0);
        $artikelId = isset($_POST['artikel_id']) && $_POST['artikel_id'] !== ''
                     ? (int)$_POST['artikel_id'] : null;
        $label     = trim($_POST['label'] ?? '') ?: null;

        if ($slot < 1 || $slot > 9) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Slot muss 1–9 sein.']);
            exit;
        }
        $svc->setSchnellwahl($kasseId, $slot, $artikelId, $label);
        echo json_encode(['erfolg' => true]);
        break;

    default:
        echo json_encode(['erfolg' => false, 'fehler' => 'Unbekannte Aktion.']);
}
