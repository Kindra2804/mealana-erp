<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/modules/varianten/VariantenMusterService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$data = $_POST;
if (!isset($data['preise']) || $data['preise'] != '1') {
    $data['preise'] = '0';
}

if (!isset($data['kategorien']) || $data['kategorien'] != '1') {
    $data['kategorien'] = '0';
}

if (!isset($data['merkmale']) || $data['merkmale'] != '1') {
    $data['merkmale'] = '0';
}

if (!isset($data['lieferanten']) || $data['lieferanten'] != '1') {
    $data['lieferanten'] = '0';
}

if (!isset($data['ueberverkauf']) || $data['ueberverkauf'] != '1') {
    $data['ueberverkauf'] = '0';
}

$quell_id = (int) ($data['quell_id']);

$kopierData = array_intersect_key($data, array_flip([
    'name',
    'artikelnummer',
    'preise',
    'kategorien',
    'merkmale',
    'lieferanten',
    'ueberverkauf',
    'varianten'
]));

$service = new ArtikelService();
$result = $service->kopiere($quell_id, $kopierData);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Artikel wurde kopiert!';

    // Varianten mitkopieren: gleiches Muster wie die Massenaktion, Quelle = Master
    if (($data['varianten'] ?? '') === '1') {
        $vm = (new VariantenMusterService())->uebernehmen($quell_id, (int)$result['id'], null, $_SESSION['benutzer']['id'] ?? null);
        if ($vm['erfolg']) {
            $_SESSION['erfolg'] = 'Artikel wurde kopiert, ' . ($vm['neu'] ?? 0) . ' Varianten angelegt!';
        } else {
            $_SESSION['fehler'] = 'Artikel wurde kopiert, aber die Varianten nicht: ' . $vm['fehler'];
        }
    }
    header('Location: detail.php?id=' . $result['id']);

    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $kopierData;
    header('Location: kopieren.php?id=' . $quell_id);
    exit;
}
