<?php
/** Partner-Auszahlung stornieren (Bar aus der Kasse wird per Kassenbuch-Einlage zurückgebucht) */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerKontoService.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
echo json_encode((new PartnerKontoService())->auszahlungStornieren(
    (int)($input['id'] ?? 0),
    (int)($_SESSION['benutzer']['id'] ?? 0)
));
