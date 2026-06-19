<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';

$id      = (int) ($_GET['id']       ?? 0);
$kundeId = (int) ($_GET['kunde_id'] ?? 0);

if ($id && $kundeId) {
    $service = new KundenService();
    $service->adresseLoeschen($id);
}

header('Location: detail.php?id=' . $kundeId . '&tab=adressen');
exit;
