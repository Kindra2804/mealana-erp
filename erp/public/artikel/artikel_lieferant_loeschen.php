<?php
// Entfernt eine Lieferanten-Zuordnung (artikel_lieferanten) eines Artikels -- 🗑️ im Tab Lieferanten.
// Vorher zeigte der Knopf auf artikel/delete.php mit der Zuordnungs-ID und deaktivierte so einen fremden Artikel.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/core/Logger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$alId      = (int) ($_POST['al_id'] ?? 0);
$artikelId = (int) ($_POST['artikel_id'] ?? 0);
if (!$alId || !$artikelId) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Parameter']);
    exit;
}

$pdo = Database::getInstance();
$st  = $pdo->prepare("SELECT lieferant_id FROM artikel_lieferanten WHERE id = :id AND artikel_id = :artikel_id");
$st->execute(['id' => $alId, 'artikel_id' => $artikelId]);
$lieferantId = $st->fetchColumn();
if ($lieferantId === false) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Zuordnung nicht gefunden']);
    exit;
}

$pdo->prepare("DELETE FROM artikel_lieferanten WHERE id = :id AND artikel_id = :artikel_id")
    ->execute(['id' => $alId, 'artikel_id' => $artikelId]);
Logger::log('artikel.lieferant.entfernen', 'artikel_lieferanten', $alId, ['artikel_id' => $artikelId, 'lieferant_id' => (int) $lieferantId]);

echo json_encode(['erfolg' => true]);
