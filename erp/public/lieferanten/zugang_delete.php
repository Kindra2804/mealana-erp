<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

$id           = (int) ($_GET['id'] ?? 0);
$lieferant_id = (int) ($_GET['lieferant_id'] ?? 0);

if ($id <= 0 || $lieferant_id <= 0) {
    header('Location: liste.php');
    exit;
}

$service = new LieferantenService();
$result  = $service->deleteZugang($id);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Zugang wurde gelöscht.';
} else {
    $_SESSION['fehler'] = $result['fehler'];
}

header('Location: detail.php?id=' . $lieferant_id . '&tab=zugaenge');
exit;
