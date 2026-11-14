<?php
/**
 * Massenaktion "Zu Mengen-Aktion hinzufügen" (Artikelliste → js/mengen_aktionen.js)
 * GET:  Liste der Mengen-Aktionen (nicht abgelaufen) für die Auswahl
 * POST: {aktion_id, ids:[]} → Artikel einzeln zuordnen
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/aktionen/MengenAktionService.php';

header('Content-Type: application/json');

try {
    $svc = new MengenAktionService();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $liste = array_values(array_filter($svc->alle(), fn($a) => $a['status'] !== 'abgelaufen'));
        $liste = array_map(fn($a) => ['id' => (int) $a['id'], 'name' => $a['name'], 'badge_text' => $a['badge_text'], 'status' => $a['status']], $liste);
        echo json_encode(['erfolg' => true, 'aktionen' => $liste]);
        exit;
    }
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $neu = $svc->artikelHinzufuegen((int) ($in['aktion_id'] ?? 0), (array) ($in['ids'] ?? []));
    $svc->uebertrageAlle((int) $_SESSION['benutzer']['id']);   // gleich an die Shops (nur bei Änderung)
    echo json_encode(['erfolg' => true, 'neu' => $neu]);
} catch (Throwable $e) {
    echo json_encode(['erfolg' => false, 'fehler' => $e->getMessage()]);
}
