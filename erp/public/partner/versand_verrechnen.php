<?php
/** Versandkosten an einen Online-Partner jetzt verrechnen (gleiche Logik wie cron/fachmiete.php) */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerVersandService.php';

header('Content-Type: application/json');
$partnerId = (int)($_POST['partner_id'] ?? 0) ?: null;
echo json_encode((new PartnerVersandService())->verrechnen((int)($_SESSION['benutzer']['id'] ?? 0), null, $partnerId));
