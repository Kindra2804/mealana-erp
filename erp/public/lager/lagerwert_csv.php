<?php
/**
 * Bewertete Bestandsliste als CSV (Excel-tauglich: UTF-8 mit BOM, Semikolon, Komma-Dezimalen).
 * ?id=N → festgehaltener Schnappschuss, ohne id → aktueller Stand.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/statistik/LagerwertService.php';

$service = new LagerwertService();
$id      = (int)($_GET['id'] ?? 0);

if ($id) {
    $snapshot = $service->findSnapshot($id);
    if (!$snapshot) {
        http_response_code(404);
        exit('Schnappschuss nicht gefunden');
    }
    $positionen = $service->findSnapshotPositionen($id);
    $dateiname  = 'lagerwert_' . date('Y-m-d', strtotime($snapshot['stichtag'])) . '_' . $snapshot['anlass'] . '.csv';
} else {
    $positionen = $service->berechne();
    $dateiname  = 'lagerwert_aktuell_' . date('Y-m-d_Hi') . '.csv';
}

$zahl = fn(float $wert, int $stellen) => number_format($wert, $stellen, ',', '');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $dateiname . '"');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");
fputcsv($out, ['Lager', 'Artikelnummer', 'Artikel', 'Menge', 'EK netto', 'EK-Quelle', 'Wert netto'], ';');

$summe = 0.0;
foreach ($positionen as $p) {
    fputcsv($out, [
        $p['lager_name'],
        $p['artikelnummer'],
        $p['artikel_name'],
        $zahl((float)$p['menge'], 3),
        $zahl((float)$p['ek_netto'], 4),
        LagerwertService::EK_QUELLEN[$p['ek_quelle']] ?? $p['ek_quelle'],
        $zahl((float)$p['wert'], 2),
    ], ';');
    $summe += (float)$p['wert'];
}
fputcsv($out, ['', '', 'Summe', '', '', '', $zahl($summe, 2)], ';');
fclose($out);
