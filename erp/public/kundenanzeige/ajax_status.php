<?php
// Bewusst OHNE auth_check.php: das Kundenanzeige-Tablet steht unbeaufsichtigt neben
// der Kasse und ist nie eingeloggt (Kiosk-Charakter, wie kasse_bon_offline.js für
// die Messe). Der Endpunkt gibt nur den aktuell laufenden Warenkorb preis — keine
// Kundendaten, keine internen/sensiblen Felder.
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/modules/kasse/KundenanzeigeService.php';
require_once __DIR__ . '/../../src/core/Database.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$kasseId = (int)($_GET['kasse_id'] ?? 0);
if (!$kasseId) {
    http_response_code(400);
    echo json_encode(['fehler' => 'kasse_id fehlt']);
    exit;
}

$status = (new KundenanzeigeService())->leseStatus($kasseId);

$db = Database::getInstance();
$qrAktiv = $db->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'kundenanzeige_qr_aktiv'")
    ->fetchColumn();
$status['qr_aktiv'] = $qrAktiv === '1';

echo json_encode($status);
