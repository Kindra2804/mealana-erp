<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/hersteller/HerstellerService.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: liste.php');
    exit;
}

$service = new HerstellerService();
$service->delete($id);

header('Location: liste.php');
exit;
