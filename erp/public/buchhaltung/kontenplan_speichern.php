<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kontenplan.php');
    exit;
}

$db          = Database::getInstance();
$id          = (int)($_POST['id'] ?? 0);
$kontonummer = trim($_POST['kontonummer'] ?? '');
$name        = trim($_POST['name'] ?? '');
$typ         = $_POST['typ'] ?? '';
$aktiv       = isset($_POST['aktiv']) ? 1 : 0;

$erlaubteTypen = ['erloes', 'aufwand', 'steuer', 'bank', 'kasse'];

$fehler = [];
if ($kontonummer === '') $fehler[] = 'Kontonummer ist Pflichtfeld.';
if ($name === '')        $fehler[] = 'Name ist Pflichtfeld.';
if (!in_array($typ, $erlaubteTypen, true)) $fehler[] = 'Ungültiger Typ.';

if (empty($fehler)) {
    $dup = $db->prepare("SELECT id FROM kontenplan WHERE kontonummer = :k AND id != :id");
    $dup->execute([':k' => $kontonummer, ':id' => $id]);
    if ($dup->fetch()) $fehler[] = 'Diese Kontonummer ist bereits vergeben.';
}

if (!empty($fehler)) {
    $_SESSION['fehler'] = $fehler;
    header('Location: kontenplan.php');
    exit;
}

if ($id > 0) {
    $db->prepare("
        UPDATE kontenplan SET kontonummer = :k, name = :n, typ = :t, aktiv = :a WHERE id = :id
    ")->execute([':k' => $kontonummer, ':n' => $name, ':t' => $typ, ':a' => $aktiv, ':id' => $id]);
    $_SESSION['erfolg'] = "Konto „{$kontonummer} {$name}“ aktualisiert.";
} else {
    $db->prepare("
        INSERT INTO kontenplan (kontonummer, name, typ, aktiv) VALUES (:k, :n, :t, :a)
    ")->execute([':k' => $kontonummer, ':n' => $name, ':t' => $typ, ':a' => $aktiv]);
    $_SESSION['erfolg'] = "Konto „{$kontonummer} {$name}“ angelegt.";
}

header('Location: kontenplan.php');
exit;
