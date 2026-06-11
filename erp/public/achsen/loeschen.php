<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/achsen/AchsenService.php';

$id = (int)($_POST['id'] ?? 0);
$service = new AchsenService();
$ergebnis = $service->delete($id);

if ($ergebnis['erfolg']) {
    $_SESSION['erfolg'] = 'Achse gelöscht';
} else {
    $_SESSION['fehler'] = $ergebnis['fehler'];
}
header('Location: liste.php');
exit;
