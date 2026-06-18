<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$name                = trim($_POST['name'] ?? '');
$parentId            = (int)($_POST['parent_id'] ?? 0) ?: null;
$istAktionsKategorie = !empty($_POST['ist_aktions_kategorie']);

$service  = new ArtikelService();
$result   = $service->createKategorie($name, $parentId, $istAktionsKategorie);

echo json_encode($result);
