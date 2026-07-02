<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/BfrService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: nacherfassung.php');
    exit;
}

$typ = $_POST['typ'] ?? '';
$id  = (int)($_POST['id'] ?? 0);
$service = new BfrService();

if ($typ === 'bon') {
    $ergebnis = $service->retryBeleg($id);
} elseif ($typ === 'nullbeleg') {
    $ergebnis = $service->retryNullbeleg($id);
} else {
    $ergebnis = ['erfolg' => false, 'fehler' => 'Unbekannter Belegtyp.'];
}

if ($ergebnis['erfolg']) {
    $_SESSION['erfolg'] = 'Signatur erneut versucht.';
} else {
    $_SESSION['fehler'] = $ergebnis['fehler'] ?? 'Signatur weiterhin fehlgeschlagen.';
}

header('Location: nacherfassung.php');
exit;
