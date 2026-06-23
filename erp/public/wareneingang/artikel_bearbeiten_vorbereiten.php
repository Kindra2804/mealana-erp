<?php
require_once __DIR__ . '/../includes/auth_check.php';

$artikelId    = (int)($_GET['artikel_id']    ?? 0);
$bestellungId = (int)($_GET['bestellung_id'] ?? 0);

if (!$artikelId || !$bestellungId) {
    header('Location: /mealana/wareneingang/index.php');
    exit;
}

$_SESSION['we_rueckkehr'] = '/mealana/wareneingang/detail.php?bestellung_id=' . $bestellungId;
header('Location: /mealana/artikel/bearbeiten.php?id=' . $artikelId);
exit;
