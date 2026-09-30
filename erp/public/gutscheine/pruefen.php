<?php
/**
 * Kasse: Gutschein-Code vor dem Bezahlen prüfen (Restguthaben/Status anzeigen).
 * Bucht nichts -- die eigentliche Einlösung passiert erst in kasse/bon_speichern.php
 * nach erfolgreich signiertem Bon.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/gutscheine/GutscheinService.php';

header('Content-Type: application/json');

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Kein Code angegeben.']);
    exit;
}

$ergebnis = (new GutscheinService())->pruefeEinloesbar($code);

// Auskunftsmodus (Kasse → Menü → "Gutschein abfragen"): immer alle Daten zurückgeben,
// auch für eingelöste/abgelaufene/stornierte Codes -- Kunde fragt "was ist noch drauf?"
if (!empty($_GET['info'])) {
    require_once __DIR__ . '/../../src/modules/gutscheine/GutscheinRepository.php';
    $repo = new GutscheinRepository();
    $g = (new GutscheinService())->findeCodeTolerant($code);
    if (!$g) {
        echo json_encode(['erfolg' => false, 'fehler' => 'Gutschein-Code nicht gefunden.']);
        exit;
    }
    $statusText = [
        'aktiv' => 'Aktiv', 'teilweise' => 'Teilweise eingelöst', 'eingeloest' => 'Vollständig eingelöst',
        'abgelaufen' => 'Abgelaufen', 'storniert' => 'Storniert',
    ];
    $g = $repo->findById((int)$g['id']); // Status kann durch die Prüfung auf 'abgelaufen' gewechselt sein
    echo json_encode([
        'erfolg'       => true,
        'code'         => $g['code'],
        'status'       => $g['status'],
        'status_text'  => $statusText[$g['status']] ?? $g['status'],
        'betrag'       => (float)$g['betrag'],
        'restguthaben' => (float)$g['restguthaben'],
        'gueltig_bis'  => $g['gueltig_bis'],
        'ausgestellt'  => $g['erstellt_am'],
        'empfaenger'   => $g['empfaenger_name'],
        'einloesbar'   => $ergebnis['erfolg'],
        'hinweis'      => $ergebnis['erfolg'] ? null : implode(' ', $ergebnis['fehler'] ?? []),
        'nachfolger'   => $ergebnis['nachfolger'] ?? null,
    ]);
    exit;
}

if (!$ergebnis['erfolg']) {
    echo json_encode([
        'erfolg'     => false,
        'fehler'     => implode(' ', $ergebnis['fehler'] ?? ['Unbekannter Fehler']),
        'nachfolger' => $ergebnis['nachfolger'] ?? null,
    ]);
    exit;
}

$g = $ergebnis['gutschein'];
echo json_encode([
    'erfolg'       => true,
    'code'         => $g['code'],
    'restguthaben' => (float)$g['restguthaben'],
    'gueltig_bis'  => $g['gueltig_bis'],
]);
