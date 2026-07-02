<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/BfrService.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt.']);
    exit;
}

$kasseId    = (int)($_POST['kasse_id'] ?? 1);
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

$ergebnis = (new BfrService())->erstelleNullbeleg($kasseId, 'manuell', $benutzerId ?: null);

$fehlertexte = [
    'kein_bfr_konfiguriert' => 'Für diese Kasse ist keine BFR-URL konfiguriert.',
    'bfr_nicht_erreichbar'  => 'BFR ist gerade nicht erreichbar — Nullbeleg bleibt ausstehend und wird beim nächsten Bon automatisch nachgeholt.',
    'antwort_ungueltig'     => 'Antwort vom BFR war nicht lesbar.',
    'rn_stimmt_nicht'       => 'Die konfigurierte RKSV-Kassen-ID stimmt nicht mit dem BFR überein.',
    'abgelehnt'             => 'BFR hat die Signatur abgelehnt.',
];

if ($ergebnis['erfolg']) {
    echo json_encode(['erfolg' => true, 'beleg_nr' => $ergebnis['beleg_nr']]);
} else {
    echo json_encode([
        'erfolg' => false,
        'fehler' => $fehlertexte[$ergebnis['grund']] ?? 'Nullbeleg fehlgeschlagen.',
    ]);
}
