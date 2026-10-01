<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerLagerService.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
echo json_encode((new PartnerLagerService())->belegBuchen(
    (int)($input['partner_id'] ?? 0),
    (string)($input['typ'] ?? ''),
    (array)($input['positionen'] ?? []),
    trim((string)($input['notiz'] ?? '')) ?: null,
    (int)($_SESSION['benutzer']['id'] ?? 0)
));
