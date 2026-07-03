<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/modules/lager/LagerService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: frei.php'); exit;
}

$data = $_POST;
foreach ($data as $k => $v) {
    if ($v === '') $data[$k] = null;
}
$data['benutzer_id'] = $_SESSION['benutzer']['id'] ?? null;
$data['referenz']    = 'Freier WE';

$service = new LagerService();
$result  = $service->wareneingang($data);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Wareneingang gebucht: ' . (int)$_POST['menge'] . ' Stk.';
} else {
    $_SESSION['fehler'] = is_array($result['fehler']) ? implode(', ', $result['fehler']) : $result['fehler'];
}
header('Location: ' . BASE_PATH . '/packplatz/wareneingang/frei.php');
exit;
