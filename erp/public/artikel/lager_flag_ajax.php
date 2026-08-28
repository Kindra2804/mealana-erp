<?php

/**
 * AJAX-Handler für das generische "Keine Lagerbestandsführung"-Flag am Artikel
 * (auf Bestellung gefertigte Artikel wie Konfigurator-Layouts, aber auch für
 * andere Artikeltypen ohne eigenen Lagerbestand nutzbar).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/logger.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelRepository.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';
$repo   = new ArtikelRepository();

try {
    switch ($action) {

        case 'toggle':
            $artikelId = (int)($input['artikel_id'] ?? 0);
            $aktiv     = !empty($input['aktiv']);
            if (!$artikelId) throw new Exception('Ungültiger Artikel');
            $repo->setKeineLagerbestandsfuehrung($artikelId, $aktiv);
            Logger::log('artikel.keine_lagerbestandsfuehrung', 'artikel', $artikelId, [
                'artikel' => $artikelId,
                'aktiv'   => $aktiv,
            ]);
            echo json_encode(['erfolg' => true, 'keine_lagerbestandsfuehrung' => $aktiv]);
            break;

        default:
            echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Funktion']);
    }
} catch (Exception $e) {
    echo json_encode(['erfolg' => false, 'fehler' => $e->getMessage()]);
}
