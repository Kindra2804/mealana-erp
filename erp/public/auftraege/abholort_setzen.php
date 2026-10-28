<?php
/**
 * Auftrag → Abholort ändern (nur bei Selbstabholung), siehe AbholortService::setzen().
 * POST: auftrag_id, abholort_id  → JSON
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/AbholortService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

echo json_encode((new AbholortService())->setzen(
    (int)($_POST['auftrag_id'] ?? 0),
    !empty($_POST['abholort_id']) ? (int)$_POST['abholort_id'] : null
));
