<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$id                  = (int)($_POST['id'] ?? 0);
$name                = trim($_POST['name'] ?? '');
$parentId            = (int)($_POST['parent_id'] ?? 0) ?: null;
$istAktionsKategorie = !empty($_POST['ist_aktions_kategorie']);

if (!$id) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige ID']);
    exit;
}

$service = new ArtikelService();
echo json_encode($service->updateKategorie($id, $name, $parentId, $istAktionsKategorie));
