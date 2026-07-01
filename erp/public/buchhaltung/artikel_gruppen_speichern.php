<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: artikel_gruppen.php');
    exit;
}

$db       = Database::getInstance();
$id       = (int)($_POST['id'] ?? 0);
$kontoNr  = trim($_POST['konto_nr'] ?? '');
$name     = trim($_POST['name'] ?? '');
$sort     = (int)($_POST['sortierung'] ?? 0);
$aktiv    = isset($_POST['aktiv']) ? 1 : 0;

$fehler = [];
if ($kontoNr === '') $fehler[] = 'Kontonummer ist Pflichtfeld.';
if ($name === '')    $fehler[] = 'Name ist Pflichtfeld.';

if (empty($fehler)) {
    // Duplikat-Check (Konto-Nr eindeutig)
    $dup = $db->prepare("SELECT id FROM artikel_gruppen WHERE konto_nr = :k AND id != :id");
    $dup->execute([':k' => $kontoNr, ':id' => $id]);
    if ($dup->fetch()) $fehler[] = 'Diese Kontonummer ist bereits vergeben.';
}

if (!empty($fehler)) {
    $_SESSION['fehler'] = $fehler;
    header('Location: artikel_gruppen.php');
    exit;
}

if ($id > 0) {
    $stmt = $db->prepare("
        UPDATE artikel_gruppen
        SET konto_nr = :k, name = :n, sortierung = :s, aktiv = :a
        WHERE id = :id
    ");
    $stmt->execute([':k' => $kontoNr, ':n' => $name, ':s' => $sort, ':a' => $aktiv, ':id' => $id]);
    $_SESSION['erfolg'] = "Artikelgruppe „{$name}" aktualisiert.";
} else {
    $stmt = $db->prepare("
        INSERT INTO artikel_gruppen (konto_nr, name, sortierung, aktiv)
        VALUES (:k, :n, :s, :a)
    ");
    $stmt->execute([':k' => $kontoNr, ':n' => $name, ':s' => $sort, ':a' => $aktiv]);
    $_SESSION['erfolg'] = "Artikelgruppe „{$name}" angelegt.";
}

header('Location: artikel_gruppen.php');
exit;
