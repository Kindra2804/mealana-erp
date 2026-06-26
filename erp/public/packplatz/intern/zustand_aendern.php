<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/core/Logger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Anfrage']); exit;
}

$artikelId   = (int)($_POST['artikel_id'] ?? 0);
$neuerZustand = trim($_POST['zustand'] ?? '');
$benutzerId  = (int)($_SESSION['benutzer']['id'] ?? 0);

$erlaubteZustaende = ['neu','gebraucht','generalueberholt','beschaedigt','retour','demo','muster','ausstellungsstueck'];
if (!$artikelId || !in_array($neuerZustand, $erlaubteZustaende, true)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Daten']); exit;
}

$db = Database::getInstance();

$stmt = $db->prepare("SELECT zustand FROM artikel WHERE id = :id");
$stmt->execute([':id' => $artikelId]);
$alter = $stmt->fetchColumn();

if ($alter === false) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Artikel nicht gefunden']); exit;
}

if ($alter === $neuerZustand) {
    echo json_encode(['erfolg' => true]); exit;
}

$db->prepare("UPDATE artikel SET zustand = :z WHERE id = :id")
   ->execute([':z' => $neuerZustand, ':id' => $artikelId]);

Logger::log('artikel.zustand_geaendert', 'artikel', $artikelId, [
    'von' => $alter,
    'zu'  => $neuerZustand,
], $benutzerId);

echo json_encode(['erfolg' => true]);
