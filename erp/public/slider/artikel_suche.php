<?php
/** Shop-Slider: Artikelsuche für das Button-Ziel (nur Artikel, die im gewählten Shop schon angelegt sind). */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/slider/SliderRepository.php';

header('Content-Type: application/json');
$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo '[]';
    exit;
}
echo json_encode((new SliderRepository())->sucheShopArtikel((int)($_GET['shop'] ?? 0), $q), JSON_UNESCAPED_UNICODE);
