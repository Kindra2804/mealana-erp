<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/lager/picklisten.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    $_SESSION['fehler'] = 'Ungültige Anfrage.';
    header('Location: ' . BASE_PATH . '/lager/picklisten.php');
    exit;
}

$db   = Database::getInstance();
$stmt = $db->prepare("SELECT id, nummer, status FROM picklisten WHERE id = :id");
$stmt->execute([':id' => $id]);
$pl   = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pl) {
    $_SESSION['fehler'] = 'Pickliste nicht gefunden.';
    header('Location: ' . BASE_PATH . '/lager/picklisten.php');
    exit;
}

if ($pl['status'] === 'abgeschlossen') {
    $_SESSION['fehler'] = 'Abgeschlossene Picklisten können nicht gelöscht werden.';
    header('Location: ' . BASE_PATH . '/lager/picklisten.php');
    exit;
}

// Zuordnungen + Pickliste löschen
$db->prepare("DELETE FROM pickliste_auftraege WHERE pickliste_id = :id")->execute([':id' => $id]);
$db->prepare("DELETE FROM picklisten WHERE id = :id")->execute([':id' => $id]);

// PDF-Datei löschen falls vorhanden
$dateipfad = __DIR__ . '/../../storage/picklisten/' . $pl['nummer'] . '.pdf';
if (file_exists($dateipfad)) {
    unlink($dateipfad);
}

$_SESSION['erfolg'] = 'Pickliste ' . $pl['nummer'] . ' wurde gelöscht.';
header('Location: ' . BASE_PATH . '/lager/picklisten.php');
exit;
