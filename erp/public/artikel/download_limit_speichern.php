<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$artikelId = (int)($_POST['artikel_id'] ?? 0);
$limit     = trim($_POST['download_limit'] ?? '');

if ($artikelId > 0) {
    $repo = new ArtikelRepository();
    $repo->updateDownloadLimit($artikelId, $limit !== '' ? (int)$limit : null);
}

header('Location: detail.php?id=' . $artikelId . '&tab=download');
