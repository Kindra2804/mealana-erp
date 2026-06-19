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
    $artikelService->kopiereVaterRelationenZuKindern($artikelId, $result['ids']);
    header('Location: detail.php?id=' . $artikelId . '&tab=varianten');
    exit;
} else {
    header('Location: detail.php?id=' . $artikelId . '&tab=varianten&var_fehler=1');
    exit;
}
