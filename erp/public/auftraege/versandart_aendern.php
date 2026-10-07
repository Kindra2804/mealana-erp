<?php
/**
 * Auftrag → "Versandart ändern" (Abholung ↔ Versand), siehe AuftragService::versandartAendern().
 * POST: auftrag_id, lieferart, versandklasse_id, versandkosten  → JSON
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$ergebnis = (new AuftragService())->versandartAendern(
    (int)($_POST['auftrag_id'] ?? 0),
    trim($_POST['lieferart'] ?? ''),
    !empty($_POST['versandklasse_id']) ? (int)$_POST['versandklasse_id'] : null,
    (float)str_replace(',', '.', $_POST['versandkosten'] ?? '0'),
    (int)($_SESSION['benutzer']['id'] ?? 0)
);

echo json_encode($ergebnis);
