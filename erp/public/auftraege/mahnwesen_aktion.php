<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/MahnwesenService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$aktion     = $_POST['aktion'] ?? '';
$mahnungId  = (int)($_POST['mahnung_id'] ?? 0);
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);
$service    = new MahnwesenService();

switch ($aktion) {
    case 'freigeben':
        echo json_encode($service->freigeben($mahnungId, $benutzerId));
        break;
    case 'verwerfen':
        echo json_encode($service->verwerfen($mahnungId, $benutzerId));
        break;
    case 'erlassen':
        echo json_encode($service->gebuehrErlassen($mahnungId, $benutzerId));
        break;
    case 'alle_freigeben':
        $ok = 0; $fehler = [];
        foreach ($service->findVorschlaege() as $v) {
            $r = $service->freigeben((int)$v['id'], $benutzerId);
            $r['erfolg'] ? $ok++ : $fehler[] = $v['auftrag_nr'] . ': ' . $r['fehler'];
        }
        echo json_encode(['erfolg' => true, 'freigegeben' => $ok, 'fehler_liste' => $fehler]);
        break;
    default:
        echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Aktion']);
}
