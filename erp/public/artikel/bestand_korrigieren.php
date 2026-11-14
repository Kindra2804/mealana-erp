<?php
/**
 * Bestand korrigieren (Artikel → Lager → „± Bestand korrigieren“, js/bestand_korrektur.js)
 * GET  ?artikel_id=X  → aktive Lager mit Bestand je Charge + Gründe
 * POST {artikel_id, lager_id, charge, neu, grund, notiz} → LagerService::bestandKorrigieren()
 * Recht: inventur.anlegen (Jacky 2026-11-14: wer Inventur darf, darf korrigieren)
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

header('Content-Type: application/json');
try {
    $svc = new LagerService();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(['erfolg' => true, 'lager' => $svc->bestandFuerKorrektur((int)($_GET['artikel_id'] ?? 0)),
                          'gruende' => LagerService::KORREKTUR_GRUENDE]);
        exit;
    }
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    echo json_encode($svc->bestandKorrigieren(
        (int)($in['artikel_id'] ?? 0), (int)($in['lager_id'] ?? 0),
        isset($in['charge']) && $in['charge'] !== '' ? (string)$in['charge'] : null,
        (float)str_replace(',', '.', (string)($in['neu'] ?? '')), (string)($in['grund'] ?? ''),
        trim((string)($in['notiz'] ?? '')) ?: null, (int)$_SESSION['benutzer']['id']));
} catch (Throwable $e) {
    echo json_encode(['erfolg' => false, 'fehler' => $e->getMessage()]);
}
