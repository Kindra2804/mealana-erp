<?php
/**
 * Grundpreise prüfen: Speichern (JSON). Body: {aktion: "speichern", zeilen: [...]} oder {aktion: "vererben", ids: [...]}.
 * Antwort {erfolg, gespeichert | fehler[]}.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/GrundpreisPruefungService.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => ['Nur POST erlaubt']]);
    exit;
}
$daten = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($daten)) {
    echo json_encode(['erfolg' => false, 'fehler' => ['Ungültige Anfrage']]);
    exit;
}

$service = new GrundpreisPruefungService();
$ergebnis = match ($daten['aktion'] ?? '') {
    'speichern' => $service->speichere(is_array($daten['zeilen'] ?? null) ? $daten['zeilen'] : []),
    'vererben'  => $service->vererbe(is_array($daten['ids'] ?? null) ? $daten['ids'] : []),
    default     => ['erfolg' => false, 'fehler' => ['Unbekannte Aktion']],
};
echo json_encode($ergebnis, JSON_UNESCAPED_UNICODE);
