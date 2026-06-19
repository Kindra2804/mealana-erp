<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';

$id     = (int) ($_GET['id']     ?? 0);
$status = $_GET['status'] ?? '';

if ($id && $status) {
    $service = new KundenService();
    $result  = $service->statusSetzen($id, $status);
    if (!$result['erfolg']) {
        $_SESSION['fehler'] = $result['fehler'];
    }
}

header('Location: detail.php?id=' . $id);
exit;
