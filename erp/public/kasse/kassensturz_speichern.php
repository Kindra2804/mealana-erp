<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kassensturz.php'); exit;
}

$aktion  = $_POST['aktion']   ?? '';
$kasseId = (int)($_POST['kasse_id'] ?? 1);
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

$service = new KassenService();

if ($aktion === 'x_bon') {
    $result = $service->erstelleXBon($kasseId, $benutzerId);
    if ($result['erfolg']) {
        header('Location: abschluss_druck.php?id=' . $result['abschluss_id']);
        exit;
    }
    $_SESSION['fehler'] = $result['fehler'] ?? 'Fehler beim X-Bon.';
} elseif ($aktion === 'z_bon') {
    $result = $service->erstelleZBon($kasseId, $benutzerId);
    if ($result['erfolg']) {
        header('Location: abschluss_druck.php?id=' . $result['abschluss_id']);
        exit;
    }
    $_SESSION['fehler'] = $result['fehler'] ?? 'Fehler beim Z-Bon.';
} else {
    $_SESSION['fehler'] = 'Unbekannte Aktion.';
}

header('Location: kassensturz.php');
exit;
