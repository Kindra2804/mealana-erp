<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/hersteller/HerstellerService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => ['Nur POST erlaubt']]);
    exit;
}

$service = new HerstellerService();

$result = $service->save([
    'name'        => trim($_POST['name'] ?? ''),
    'handelsname' => null,
    'webseite'    => null,
    'land'        => strtoupper(trim($_POST['land'] ?? '')),
    'email'       => null,
    'logo_pfad'   => null,
    'strasse'     => null,
    'plz'         => null,
    'ort'         => null,
    'reo_name'    => null,
    'reo_strasse' => null,
    'reo_plz'     => null,
    'reo_ort'     => null,
    'reo_land'    => null,
    'reo_email'   => null,
    'notizen'     => null,
    'aktiv'       => 1,
]);

if ($result['erfolg']) {
    $h = $service->findById($result['id']);
    $result['name'] = $h['name'];
}

echo json_encode($result);
