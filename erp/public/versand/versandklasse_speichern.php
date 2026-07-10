<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db          = Database::getInstance();
$id          = (int)($_POST['id'] ?? 0);
$name        = trim($_POST['name'] ?? '');
$code        = trim($_POST['code'] ?? '') ?: null;
$kuerzel     = trim($_POST['kuerzel'] ?? '') ?: null;
$preis       = $_POST['preis_brutto'] !== '' ? (float)$_POST['preis_brutto'] : null;
$sort        = (int)($_POST['sortierung'] ?? 0);
$gruppeId    = (int)($_POST['artikel_gruppe_id'] ?? 0) ?: null;

$fehler = [];
if ($name === '')    $fehler[] = 'Name ist Pflichtfeld.';
if (!$gruppeId)      $fehler[] = 'Artikelgruppe (Konto) ist Pflichtfeld.';

if (!empty($fehler)) {
    $_SESSION['fehler'] = $fehler;
    header('Location: index.php');
    exit;
}

if ($id > 0) {
    $stmt = $db->prepare("
        UPDATE versandklassen
        SET name = :n, code = :c, kuerzel = :k, preis_brutto = :p,
            sortierung = :s, artikel_gruppe_id = :ag
        WHERE id = :id
    ");
    $stmt->execute([':n' => $name, ':c' => $code, ':k' => $kuerzel, ':p' => $preis,
                    ':s' => $sort, ':ag' => $gruppeId, ':id' => $id]);
    $_SESSION['erfolg'] = "Versandklasse „{$name}“ aktualisiert.";
} else {
    $stmt = $db->prepare("
        INSERT INTO versandklassen (name, code, kuerzel, preis_brutto, sortierung, artikel_gruppe_id)
        VALUES (:n, :c, :k, :p, :s, :ag)
    ");
    $stmt->execute([':n' => $name, ':c' => $code, ':k' => $kuerzel, ':p' => $preis,
                    ':s' => $sort, ':ag' => $gruppeId]);
    $_SESSION['erfolg'] = "Versandklasse „{$name}“ angelegt.";
}

header('Location: index.php');
exit;
