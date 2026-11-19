<?php
/**
 * Kassen-Liste / Preisliste – liefert das PDF (POST von schilder/kassenliste.php). Nichts wird gespeichert.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/schilder/KassenlisteService.php';

try {
    $vorlage = (string) ($_POST['vorlage'] ?? 'kassenliste');
    $pdf = (new KassenlisteService())->listePdf($vorlage, array_map('intval', (array) ($_POST['ids'] ?? [])), [
        'titel'       => (string) ($_POST['titel'] ?? ''),
        'untertitel'  => (string) ($_POST['untertitel'] ?? ''),
        'mit_bild'    => !empty($_POST['mit_bild']),
        'mit_barcode' => !empty($_POST['mit_barcode']),
        'gruppieren'  => !empty($_POST['gruppieren']),
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Liste konnte nicht erstellt werden: ' . $e->getMessage();
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $vorlage . '_' . date('Y-m-d_His') . '.pdf"');
echo $pdf;
