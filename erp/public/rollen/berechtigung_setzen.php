<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/rollen/RollenService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => ['Nur POST erlaubt']]);
    exit;
}

$rolleId        = (int)($_POST['rolle_id']        ?? 0);
$berechtigungId = (int)($_POST['berechtigung_id'] ?? 0);
$gewaehrt       = !empty($_POST['gewaehrt']) && $_POST['gewaehrt'] !== '0';
$eigenerRang    = (int)($_SESSION['benutzer']['rolle_rang'] ?? 0);

if (!$rolleId || !$berechtigungId) {
    echo json_encode(['erfolg' => false, 'fehler' => ['Rolle oder Berechtigung fehlt']]);
    exit;
}

echo json_encode((new RollenService())->setzeBerechtigung($eigenerRang, $rolleId, $berechtigungId, $gewaehrt));
