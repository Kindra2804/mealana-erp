<?php
/**
 * Preisschilder drucken – liefert das PDF (POST von schilder/auswahl.php) und protokolliert den Druck.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/schilder/PreisschildService.php';

try {
    $svc = new PreisschildService();
    $ids = $svc->schildArtikel((array) ($_POST['ids'] ?? []));
    $pdf = $svc->pdf((string) ($_POST['vorlage'] ?? 'messe'), $ids, [
        'start'         => (int) ($_POST['start'] ?? 1),
        'schnitt'       => !empty($_POST['schnitt']),
        'messe_prozent' => max(0, min(90, (float) str_replace(',', '.', (string) ($_POST['messe_prozent'] ?? 0)))),
        'qr_shop_id'    => (int) ($_POST['qr_shop_id'] ?? 0) ?: null,
        'farbe'         => (string) ($_POST['farbe'] ?? 'mealana'),
        'mit_bild'      => !empty($_POST['mit_bild']),
    ], (int) ($_SESSION['benutzer']['id'] ?? 0) ?: null);
} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Preisschilder konnten nicht erstellt werden: ' . $e->getMessage();
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="preisschilder_' . date('Y-m-d_His') . '.pdf"');
echo $pdf;
