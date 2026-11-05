<?php
/**
 * Shop-Slider: Listen-Aktionen. POST aktion = aktiv | loeschen | kopieren | sortieren | intervall | uebertragen
 * Antwort JSON {erfolg, fehler?}.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/slider/SliderService.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$service = new SliderService();
$id      = (int)($_POST['id'] ?? 0);
$shopId  = (int)($_POST['shop_id'] ?? 0);

$ergebnis = match ($_POST['aktion'] ?? '') {
    'aktiv'       => $service->setAktiv($id, !empty($_POST['aktiv'])),
    'loeschen'    => $service->loesche($id),
    'kopieren'    => $service->kopiere($id, (int)($_POST['ziel_shop_id'] ?? 0)),
    'sortieren'   => $service->sortiere($shopId, array_map('intval', $_POST['ids'] ?? [])),
    'intervall'   => (function () use ($service, $shopId) {
        $sek = max(3, min(20, (int)($_POST['sekunden'] ?? 6)));
        $service->getRepo()->setIntervall($shopId, $sek);
        return ['erfolg' => true];
    })(),
    'uebertragen' => $service->uebertrage($shopId),
    default       => ['erfolg' => false, 'fehler' => 'Unbekannte Aktion'],
};
echo json_encode($ergebnis, JSON_UNESCAPED_UNICODE);
