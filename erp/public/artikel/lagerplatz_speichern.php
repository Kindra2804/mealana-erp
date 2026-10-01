<?php
/**
 * Stamm-/Nachfüllplatz für einen oder mehrere Artikel setzen (Artikel-Detail, Massenaktion
 * Artikelliste, Wareneingang). JSON: {ids:[..], stammplatz_id?, nachfuellplatz_id?} --
 * nur übergebene Felder werden geändert, leerer Wert = Platz entfernen.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$ids   = array_values(array_filter(array_map('intval', (array)($input['ids'] ?? []))));
$felder = array_intersect_key($input, ['stammplatz_id' => 1, 'nachfuellplatz_id' => 1]);

if (!$ids) {
    echo json_encode(['erfolg' => false, 'fehler' => ['Keine Artikel gewählt.']]);
    exit;
}
echo json_encode((new LagerService())->setzeArtikelLagerplaetze($ids, $felder));
