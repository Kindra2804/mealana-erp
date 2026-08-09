<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelController.php';
require_once __DIR__ . '/../../src/core/Database.php';

function csvFeld(string $wert): string
{
    return str_contains($wert, ';') || str_contains($wert, '"')
        ? '"' . str_replace('"', '""', $wert) . '"'
        : $wert;
}

$controller = new ArtikelController();

$statusFilter    = $_GET['status_filter'] ?? '';
$qualitaetFilter = in_array($statusFilter, ['keine_ean', 'doppelte_ean', 'keine_bilder', 'keine_gruppe', 'keine_hersteller']) ? $statusFilter : '';

$filter = [
    'q'               => trim($_GET['q'] ?? ''),
    'hersteller_id'   => (int)($_GET['hersteller_id'] ?? 0) ?: null,
    'artikeltyp_id'   => (int)($_GET['artikeltyp_id'] ?? 0) ?: null,
    'nurMitBestand'   => isset($_GET['nurMitBestand']),
    'mitInaktiven'    => isset($_GET['inaktive']) || $statusFilter === 'inaktiv',
    'status_filter'   => $statusFilter,
    'kategorie_ids'   => null,
    'nurKategorielos' => $statusFilter === 'ohnekat',
    'qualitaet'       => $qualitaetFilter,
    'kanal_shop_id'   => (int)($_GET['kanal_filter'] ?? 0) ?: null,
    'sort'            => 'artikelnummer',
    'dir'             => 'asc',
];

$artikel = $controller->index($filter, 100000, 0);

$zeilen = ['Artikelnummer;Name;Typ;Artikelgruppe;Einheit;Hersteller'];
foreach ($artikel as $a) {
    $zeilen[] = implode(';', [
        csvFeld($a['artikelnummer'] ?? ''),
        csvFeld($a['name'] ?? ''),
        csvFeld($a['artikeltyp_name'] ?? ''),
        csvFeld($a['artikelgruppe'] ?? ''),
        csvFeld($a['einheit_kuerzel'] ?? ''),
        csvFeld($a['hersteller'] ?? ''),
    ]);
}
$inhalt = "\xEF\xBB\xBF" . implode("\r\n", $zeilen) . "\r\n";

$dateiname = 'artikel_kontrollliste_' . date('Ymd') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $dateiname . '"');
header('Content-Length: ' . strlen($inhalt));
echo $inhalt;
