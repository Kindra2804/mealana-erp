<?php
/**
 * Liefert die Lagerbewegungen-Tabelle (HTML-Fragment) eines Artikels.
 * Ohne charge-Parameter: die letzten 10 (alle Chargen gemischt).
 * Mit charge-Parameter: die vollständige Historie dieser einen Charge (EK bis letzter Verkauf).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

if (!function_exists('formatBestand')) {
    function formatBestand(int|float|string $wert): string
    {
        $v = (float) $wert;
        return $v == (int) $v
            ? number_format($v, 0, ',', '.')
            : number_format($v, 3, ',', '.');
    }
}

$artikelId = (int)($_GET['artikel_id'] ?? 0);
$charge    = trim($_GET['charge'] ?? '');
if ($charge === '') {
    $charge = null;
}

if (!$artikelId) {
    http_response_code(400);
    echo 'Fehlende Artikel-ID.';
    exit;
}

$service      = new LagerService();
$bewegungslog = $service->getBewegungslog($artikelId, $charge);

include __DIR__ . '/bewegungslog_tabelle.php';
