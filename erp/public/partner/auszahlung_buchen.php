<?php
/** Auszahlung an einen Partner buchen (Fremdrechnung/Spende) → Zahlungsaviso ZA-… */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerKontoService.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
echo json_encode((new PartnerKontoService())->auszahlungBuchen(
    (int)($input['partner_id'] ?? 0),
    $input,
    (int)($_SESSION['benutzer']['id'] ?? 0)
));
