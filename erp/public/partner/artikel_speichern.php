<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerLagerService.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
echo json_encode((new PartnerLagerService())->artikelSpeichern((int)($input['partner_id'] ?? 0), $input));
