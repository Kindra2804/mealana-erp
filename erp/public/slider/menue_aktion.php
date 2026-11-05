<?php
/**
 * Shop-Menü: Speichern und Listen-Aktionen. POST aktion = speichern | aktiv | loeschen | sortieren | vorschlag | orte | uebertragen
 * Antwort JSON {erfolg, fehler?}.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/slider/MenueService.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$service = new MenueService();
$id      = (int)($_POST['id'] ?? 0);
$shopId  = (int)($_POST['shop_id'] ?? 0);

$ergebnis = match ($_POST['aktion'] ?? '') {
    'speichern'   => $service->speichere($_POST),
    'aktiv'       => $service->setAktiv($id, !empty($_POST['aktiv'])),
    'loeschen'    => $service->loesche($id),
    'sortieren'   => $service->sortiere($shopId, array_map('intval', $_POST['ids'] ?? [])),
    'vorschlag'   => $service->vorschlag($shopId),
    'orte'        => (function () use ($service, $shopId) {
        $orte = implode(',', array_filter(array_map(fn($o) => preg_replace('/[^a-z0-9_-]/i', '', trim($o)), explode(',', (string)($_POST['orte'] ?? '')))));
        if ($orte === '') return ['erfolg' => false, 'fehler' => 'Bitte mindestens einen Menü-Ort angeben (z. B. menu_1).'];
        $service->getRepo()->setOrte($shopId, $orte);
        return ['erfolg' => true];
    })(),
    'uebertragen' => $service->uebertrage($shopId),
    default       => ['erfolg' => false, 'fehler' => 'Unbekannte Aktion'],
};
echo json_encode($ergebnis, JSON_UNESCAPED_UNICODE);
