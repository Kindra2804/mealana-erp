<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KundenanzeigeService.php';

header('Content-Type: application/json');

$kasseId = (int)($_POST['kasse_id'] ?? 0);
$zustand = trim($_POST['zustand'] ?? '');
$payload = json_decode($_POST['payload'] ?? '{}', true) ?: [];

if (!$kasseId || !$zustand) {
    http_response_code(400);
    echo json_encode(['erfolg' => false]);
    exit;
}

(new KundenanzeigeService())->schreibeStatus($kasseId, $zustand, $payload);

echo json_encode(['erfolg' => true]);
