<?php
/**
 * Massenaktion "Merkmal setzen" (Artikelliste).
 * GET:  Merkmale mit Werten für das Fenster (JSON)
 * POST: {ids:[], merkmal_id, wert_ids:[], modus: ersetzen|hinzufuegen|entfernen}
 * Gewählte Väter nehmen ihre Farben mit (MerkmalService::massenSetzen).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/MerkmaleRepository.php';
require_once __DIR__ . '/../../src/modules/merkmale/MerkmalService.php';
require_once __DIR__ . '/../../src/core/Logger.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $liste = array_map(fn($m) => [
            'id' => (int) $m['id'], 'name' => $m['name'], 'slug' => $m['slug'], 'mehrfach' => (bool) $m['mehrfach_auswahl'],
            'werte' => array_map(fn($w) => ['id' => (int) $w['id'], 'wert' => $w['wert']], $m['werte']),
        ], (new MerkmaleRepository())->findAllMitWerten());
        // automatisch abgeleitete Bereiche nicht zur Auswahl anbieten
        echo json_encode(['erfolg' => true, 'merkmale' => array_values(array_filter($liste, fn($m) => !in_array($m['slug'], ['lauflaenge_bereich', 'maschenprobe_10cm'], true)))]);
        exit;
    }

    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $erg = (new MerkmalService())->massenSetzen($in['ids'] ?? [], (int) ($in['merkmal_id'] ?? 0), $in['wert_ids'] ?? [], (string) ($in['modus'] ?? ''));
    Logger::log('artikel.merkmal_massen', 'merkmale', (int) ($in['merkmal_id'] ?? 0),
        ['modus' => $in['modus'] ?? '', 'wert_ids' => $in['wert_ids'] ?? [], 'artikel' => $erg['artikel'], 'gesamt' => $erg['gesamt']]);
    echo json_encode(['erfolg' => true] + $erg);
} catch (Throwable $e) {
    echo json_encode(['erfolg' => false, 'fehler' => $e->getMessage()]);
}
