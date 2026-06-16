<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/varianten/VariantenRepository.php';
require_once __DIR__ . '/../../src/core/Logger.php';

header('Content-Type: application/json');

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$artikelId = (int)($body['artikel_id'] ?? 0);
$achsenRaw = $body['achsen'] ?? [];

if ($artikelId <= 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Artikel-ID']);
    exit;
}
if (!is_array($achsenRaw)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Daten']);
    exit;
}

$repo = new VariantenRepository();

if ($repo->isKindArtikel($artikelId)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Kind-Artikel können keine eigenen Achsen haben']);
    exit;
}

// Gewünschten Zustand aus Request aufbauen
$requestedAchsenIds = [];
$requestedWerte     = []; // [{achse_id, wert, sort_order}]

foreach ($achsenRaw as $achse) {
    $achseId = (int)($achse['id'] ?? 0);
    if ($achseId <= 0) continue;
    $requestedAchsenIds[] = $achseId;

    $sortOrder = 0;
    foreach ($achse['werte'] ?? [] as $wertText) {
        $wertText = trim((string)$wertText);
        if ($wertText === '') continue;
        $requestedWerte[] = [
            'achse_id'   => $achseId,
            'wert'       => $wertText,
            'sort_order' => $sortOrder++,
        ];
    }
}

// Aktuellen DB-Zustand laden
$currentWerte = $repo->findWerteByArtikelId($artikelId);
$inUseIds     = $repo->findWertIdsInUse($artikelId);
$inUseSet     = array_flip($inUseIds);

// Lookup: achse_id|wert → aktueller DB-Datensatz
$currentByKey = [];
foreach ($currentWerte as $cw) {
    $currentByKey[$cw['achse_id'] . '|' . $cw['wert']] = $cw;
}

// Lookup: achse_id|wert → neuer Datensatz (für Prüfung)
$requestedKeys = [];
foreach ($requestedWerte as $rw) {
    $requestedKeys[$rw['achse_id'] . '|' . $rw['wert']] = $rw;
}

// Alte Werte löschen die nicht mehr gewünscht sind — NUR wenn nicht in Verwendung
foreach ($currentWerte as $cw) {
    $key = $cw['achse_id'] . '|' . $cw['wert'];
    if (!isset($requestedKeys[$key]) && !isset($inUseSet[$cw['id']])) {
        $repo->deleteWert((int)$cw['id']);
    }
}

// Neue Werte einfügen / bestehende sort_order aktualisieren
foreach ($requestedWerte as $rw) {
    $key = $rw['achse_id'] . '|' . $rw['wert'];
    if (isset($currentByKey[$key])) {
        $repo->updateWertSortOrder((int)$currentByKey[$key]['id'], (int)$rw['sort_order']);
    } else {
        $repo->insertWert(array_merge($rw, ['artikel_id' => $artikelId]));
    }
}

// artikel_achsen: delete-replace ist sicher (keine FK-Abhängigkeit auf diese IDs)
$repo->deleteArtikelAchsenByArtikelId($artikelId);
foreach ($requestedAchsenIds as $achseId) {
    $repo->insertArtikelAchse(['artikel_id' => $artikelId, 'achse_id' => $achseId]);
}

Logger::log('achsenUndWerte.speichern', 'artikel_achsen', $artikelId, [
    'achsen_anzahl' => count($requestedAchsenIds),
    'werte_anzahl'  => count($requestedWerte),
]);

echo json_encode(['erfolg' => true]);
