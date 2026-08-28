<?php

/**
 * AJAX-Handler für die Konfigurator-aktivierung beim Artikel.
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
            $repo->setKonfigurierbar($artikelId, $aktiv);
            Logger::log('artikel.konfigurator_aktiv', 'artikel', $artikelId, [
                'artikel' => $artikelId,
                'aktiv'   => $aktiv,
            ]);
            echo json_encode(['erfolg' => true, 'konfigurator_aktiv' => $aktiv]);
            break;

        default:
            echo json_encode(['fehler' => 'Ungültige Funktion']);
    }
} catch (Exception $e) {
    echo json_encode(['fehler' => $e->getMessage()]);
}
