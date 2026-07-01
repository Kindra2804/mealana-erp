<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: artikel_gruppen.php');
    exit;
}

$db = Database::getInstance();
$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    header('Location: artikel_gruppen.php');
    exit;
}

// Verwendungscheck
$artAnz = (int)$db->prepare("SELECT COUNT(*) FROM artikel WHERE artikel_gruppe_id = :id")
    ->execute([':id' => $id]) ? $db->query("SELECT COUNT(*) FROM artikel WHERE artikel_gruppe_id = {$id}")->fetchColumn() : 0;

$stmtArt = $db->prepare("SELECT COUNT(*) FROM artikel WHERE artikel_gruppe_id = :id");
$stmtArt->execute([':id' => $id]);
$artAnz = (int)$stmtArt->fetchColumn();

$stmtVsk = $db->prepare("SELECT COUNT(*) FROM versandklassen WHERE artikel_gruppe_id = :id");
$stmtVsk->execute([':id' => $id]);
$vskAnz = (int)$stmtVsk->fetchColumn();

if ($artAnz > 0 || $vskAnz > 0) {
    $_SESSION['fehler'] = "Gruppe kann nicht gelöscht werden: {$artAnz} Artikel und {$vskAnz} Versandklassen verwenden sie.";
    header('Location: artikel_gruppen.php');
    exit;
}

$stmtName = $db->prepare("SELECT name FROM artikel_gruppen WHERE id = :id");
$stmtName->execute([':id' => $id]);
$name = $stmtName->fetchColumn();

$db->prepare("DELETE FROM artikel_gruppen WHERE id = :id")->execute([':id' => $id]);
$_SESSION['erfolg'] = "Artikelgruppe „{$name}" gelöscht.";

header('Location: artikel_gruppen.php');
exit;
