<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

$id = (int) ($_GET['id'] ?? 0);
$service = new LieferantenService();
$result = $service->delete($id);

if ($result['erfolg'] === true) {
    $_SESSION['erfolg'] = 'Lieferant wurde deaktiviert.';
} else {
    $_SESSION['fehler'] = $result['fehler'];
}

header('Location: liste.php');
exit;
