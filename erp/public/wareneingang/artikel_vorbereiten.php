<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Location: /mealana/wareneingang/index.php');
    exit;
}

$ean = trim($_GET['ean'] ?? '');

if ($ean) {
    $_SESSION['we_ean'] = $ean;
    $_SESSION['we_rueckkehr'] = '/mealana/wareneingang/index.php?ean=' . $ean;
}

header('location: /mealana/artikel/neu.php');
exit;
