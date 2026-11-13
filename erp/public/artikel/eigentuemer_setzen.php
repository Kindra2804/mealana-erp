<?php
/**
 * Eigentümer von Artikeln setzen (Migration 237): Hauptfirma oder ein Partner mit "verkauft online".
 * POST JSON {ids: [...], partner_id: <id> | ""} -> JSON. Logik: ArtikelEigentumService.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/ArtikelEigentumService.php';

header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$ids = array_map('intval', (array) ($input['ids'] ?? []));
$partnerId = !empty($input['partner_id']) ? (int) $input['partner_id'] : null;

echo json_encode((new ArtikelEigentumService())->setze($ids, $partnerId, (int) ($_SESSION['benutzer']['id'] ?? 0)), JSON_UNESCAPED_UNICODE);
