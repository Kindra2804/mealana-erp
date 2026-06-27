<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: offene_auswahl.php'); exit;
}

$oaId       = (int)($_POST['oa_id'] ?? 0);
$aktion     = $_POST['aktion'] ?? '';
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

$service = new KassenService();

if ($aktion === 'zurueck') {
    $result = $service->offeneAuswahlZurueck($oaId, $benutzerId);
    if ($result['erfolg']) {
        $_SESSION['erfolg'] = 'Artikel wurden als zurückgegeben eingebucht.';
    } else {
        $_SESSION['fehler'] = $result['fehler'];
    }
} else {
    $_SESSION['fehler'] = 'Unbekannte Aktion.';
}

header('Location: offene_auswahl.php');
exit;
