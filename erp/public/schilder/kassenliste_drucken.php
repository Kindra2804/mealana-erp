<?php
/**
 * Kassen-Liste / Preisliste / Mini-Anhänger – liefert das PDF (POST von schilder/kassenliste.php).
 * Gespeichert werden nur die Kurznamen der Mini-Anhänger (artikel_kurznamen).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/schilder/KassenlisteService.php';

try {
    $vorlage = (string) ($_POST['vorlage'] ?? 'kassenliste');
    $svc = new KassenlisteService();
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
    // Mini-Anhänger: Kurznamen werden dabei je Artikel gespeichert
    if ($vorlage === 'mini') {
        $pdf = $svc->miniPdf($ids, (array) ($_POST['anzahl'] ?? []), (array) ($_POST['kurz'] ?? []), [
            'start'   => (int) ($_POST['start'] ?? 1),
            'schnitt' => !empty($_POST['schnitt']),
        ]);
    } else $pdf = $svc->listePdf($vorlage, $ids, [
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
