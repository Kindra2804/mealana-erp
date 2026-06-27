<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kassenbuch.php'); exit;
}

$typ     = $_POST['typ']     ?? '';
$betrag  = (float)($_POST['betrag'] ?? 0);
$notiz   = trim($_POST['notiz'] ?? '') ?: null;
$kasseId = (int)($_POST['kasse_id'] ?? 1);
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

$service = new KassenService();
$result  = $service->bucheKassenbuch($typ, $betrag, $notiz, $kasseId, $benutzerId);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = ucfirst($typ) . ' über € ' . number_format($betrag, 2, ',', '.') . ' gebucht.';
} else {
    $_SESSION['fehler'] = $result['fehler'];
}

header('Location: kassenbuch.php');
exit;
