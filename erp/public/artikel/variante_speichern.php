<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$data = $_POST;
foreach ($data as $key => $value) {
    if ($value === '') $data[$key] = null;
}

$service = new ArtikelService();
$result  = $service->saveKind($data);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Variante wurde gespeichert!';
    header('Location: detail.php?id=' . (int) $data['vaterartikel_id']);
    exit;
} else {
    $_SESSION['fehler']   = $result['fehler'];
    $_SESSION['formdata'] = $data;
    header('Location: variante_neu.php?artikel_id=' . (int) $data['vaterartikel_id']);
    exit;
}
