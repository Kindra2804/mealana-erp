<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt.']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Daten.']); exit;
}

$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);
$service    = new KassenService();

$bonDaten = [
    'kasse_id'         => (int)($input['kasse_id']         ?? 1),
    'lager_id'         => (int)($input['lager_id']         ?? 1),
    'zahlungsart'      => $input['zahlungsart']             ?? 'bar',
    'bruttobetrag'     => (float)($input['bruttobetrag']   ?? 0),
    'gegeben'          => isset($input['gegeben'])          ? (float)$input['gegeben'] : null,
    'rueckgeld'        => isset($input['rueckgeld'])        ? (float)$input['rueckgeld'] : null,
    'bar_betrag'       => isset($input['bar_betrag'])       ? (float)$input['bar_betrag'] : null,
    'karten_betrag'    => isset($input['karten_betrag'])    ? (float)$input['karten_betrag'] : null,
    'gutschein_code'   => $input['gutschein_code']          ?? null,
    'gutschein_betrag' => isset($input['gutschein_betrag']) ? (float)$input['gutschein_betrag'] : null,
    'auftrag_id'       => isset($input['auftrag_id'])       ? (int)$input['auftrag_id'] : null,
    'kunden_id'        => isset($input['kunden_id'])        ? (int)$input['kunden_id'] : null,
    'notiz'            => $input['notiz']                   ?? null,
];

$positionen = $input['positionen'] ?? [];
if (empty($positionen)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Keine Positionen.']); exit;
}

// Positionen bereinigen
$sauberePositionen = [];
foreach ($positionen as $p) {
    $sauberePositionen[] = [
        'artikel_id'         => isset($p['artikel_id']) && $p['artikel_id'] ? (int)$p['artikel_id'] : null,
        'bezeichnung'        => trim($p['bezeichnung'] ?? ''),
        'ean'                => $p['ean']  ?? null,
        'menge'              => (float)($p['menge'] ?? 1),
        'einzelpreis_brutto' => (float)($p['einzelpreis_brutto'] ?? 0),
        'steuer_prozent'     => (float)($p['steuer_prozent'] ?? 20),
        'rabatt_prozent'     => (float)($p['rabatt_prozent'] ?? 0),
        'charge'             => $p['charge'] ?? null,
    ];
}

// Bruttobetrag serverseitig aus Positionen neu berechnen (kein Vertrauen auf Client-Wert)
$serverBrutto = 0;
foreach ($sauberePositionen as $p) {
    $serverBrutto += $p['menge'] * $p['einzelpreis_brutto'] * (1 - $p['rabatt_prozent'] / 100);
}
$bonDaten['bruttobetrag'] = round($serverBrutto, 2);

$result = $service->erstelleBon($bonDaten, $sauberePositionen, $benutzerId);
echo json_encode($result);
