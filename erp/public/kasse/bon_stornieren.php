<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bon_journal.php'); exit;
}

$bonId      = (int)($_POST['bon_id'] ?? 0);
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

$service = new KassenService();
$result  = $service->storniereBon($bonId, $benutzerId);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Bon storniert. Storno-Bon: ' . $result['bon_nr'];
} else {
    $_SESSION['fehler'] = $result['fehler'];
}

header('Location: bon_journal.php');
exit;
