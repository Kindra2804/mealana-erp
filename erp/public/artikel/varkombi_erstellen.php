<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/varianten/VariantenService.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$artikelId = (int)($_POST['artikel_id'] ?? 0);
$hatEigenenLagerstand  = (int)($_POST['hat_eigenen_lagerstand'] ?? 0);

if ($artikelId <= 0) {
    header('Location: liste.php');
    exit;
}

$kombis = array_filter($_POST['kombis'] ?? [], fn($k) => isset($k['selected']));

$artikelService = new ArtikelService();
$vater = $artikelService->findById($artikelId);

$variantenService = new VariantenService();

$result = $variantenService->erstelleKombinationen($vater, $hatEigenenLagerstand, $kombis);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'varkombi eingefügt';
    header('Location: varkombi_generator.php?artikel_id=' . $artikelId);
    exit;
} else {
    $_SESSION['fehler'] = ['fehler beim speichern'];
    header('Location: varkombi_generator.php?artikel_id=' . $artikelId);
    exit;
}
