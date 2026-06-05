<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

$id = (int) ($_GET['id'] ?? 0);
$lieferant_id = (int) ($_GET['lieferant_id'] ?? 0);
$service = new LieferantenService();
$result = $service->deleteVertreter($id);

if ($result['erfolg'] === true) {
    $_SESSION['erfolg'] = 'Vertreter wurde deaktiviert.';
} else {
    $_SESSION['fehler'] = $result['fehler'];
}

header('Location: detail.php?id=' . $lieferant_id);
exit;
