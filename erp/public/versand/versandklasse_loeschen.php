<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();
$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    header('Location: index.php');
    exit;
}

// Verwendungscheck (Artikel haben versandklasse_id auf artikel-Ebene)
$stmtArt = $db->prepare("SELECT COUNT(*) FROM artikel WHERE versandklasse_id = :id");
$stmtArt->execute([':id' => $id]);
$artAnz = (int)$stmtArt->fetchColumn();

if ($artAnz > 0) {
    $_SESSION['fehler'] = "Versandklasse kann nicht gelöscht werden: {$artAnz} Artikel verwenden sie.";
    header('Location: index.php');
    exit;
}

$stmtName = $db->prepare("SELECT name FROM versandklassen WHERE id = :id");
$stmtName->execute([':id' => $id]);
$name = $stmtName->fetchColumn();

$db->prepare("DELETE FROM versandklassen WHERE id = :id")->execute([':id' => $id]);
$_SESSION['erfolg'] = "Versandklasse „{$name}“ gelöscht.";

header('Location: index.php');
exit;
