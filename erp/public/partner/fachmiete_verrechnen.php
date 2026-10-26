<?php
/** Fachmiete jetzt verrechnen (gleiche Logik wie cron/fachmiete.php) */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/MietRechnungService.php';

header('Content-Type: application/json');
echo json_encode((new MietRechnungService())->verrechnen((int)($_SESSION['benutzer']['id'] ?? 0)));
