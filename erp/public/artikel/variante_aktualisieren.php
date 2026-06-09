<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$data = $_POST;

if (!isset($data['ist_auslaufartikel']) || $data['ist_auslaufartikel'] != '1') {
    $data['ist_auslaufartikel'] = '0';
}

foreach ($data as $key => $value) {
    if ($value === '') $data[$key] = null;
}

$service = new ArtikelService();
$result  = $service->kindUpdate($data);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Variante wurde aktualisiert!';
    header('Location: detail.php?id=' . (int) $data['vaterartikel_id']);
    exit;
} else {
    $_SESSION['fehler']   = $result['fehler'];
    $_SESSION['formdata'] = $data;
    header('Location: variante_bearbeiten.php?id=' . (int) $data['id']);
    exit;
}
