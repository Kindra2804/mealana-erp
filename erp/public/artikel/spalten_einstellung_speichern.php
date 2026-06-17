<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$input  = json_decode(file_get_contents('php://input'), true);
$spalten = $input['spalten'] ?? null;

if (!is_array($spalten)) {
    echo json_encode(['fehler' => 'Ungültige Daten']);
    exit;
}

$erlaubt = ['status','shops','bestand','preis','hersteller','ean','einheit',
            'kategorie','geaendert_am','ek','marge','charge',
            'merkmale','lagerplatz','letzte_inventur'];

$spalten = array_values(array_filter($spalten, fn($s) => in_array($s, $erlaubt)));

$db = Database::getInstance();
$stmt = $db->prepare("
    INSERT INTO benutzer_einstellungen (benutzer_id, schluessel, wert)
    VALUES (:uid, 'artikel_liste.spalten', :wert)
    ON DUPLICATE KEY UPDATE wert = :wert2
");
$stmt->execute([
    'uid'   => $_SESSION['benutzer']['id'],
    'wert'  => json_encode($spalten),
    'wert2' => json_encode($spalten),
]);

echo json_encode(['erfolg' => true]);
