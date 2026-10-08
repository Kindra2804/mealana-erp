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
$anKasse  = isset($_POST['an_kasse_waehlbar']) ? 1 : 0;
// Leer = Normalsatz (NULL); nur die österreichischen Sätze zulassen
$steuer   = $_POST['standard_steuer_prozent'] ?? '';
$steuer   = in_array($steuer, ['0', '10', '13', '20'], true) ? $steuer : null;

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
        SET konto_nr = :k, name = :n, sortierung = :s, aktiv = :a,
            an_kasse_waehlbar = :ka, standard_steuer_prozent = :st
        WHERE id = :id
    ");
    $stmt->execute([':k' => $kontoNr, ':n' => $name, ':s' => $sort, ':a' => $aktiv,
                    ':ka' => $anKasse, ':st' => $steuer, ':id' => $id]);
    $_SESSION['erfolg'] = "Artikelgruppe „{$name}“ aktualisiert.";
} else {
    $stmt = $db->prepare("
        INSERT INTO artikel_gruppen (konto_nr, name, sortierung, aktiv, an_kasse_waehlbar, standard_steuer_prozent)
        VALUES (:k, :n, :s, :a, :ka, :st)
    ");
    $stmt->execute([':k' => $kontoNr, ':n' => $name, ':s' => $sort, ':a' => $aktiv,
                    ':ka' => $anKasse, ':st' => $steuer]);
    $_SESSION['erfolg'] = "Artikelgruppe „{$name}“ angelegt.";
}

header('Location: artikel_gruppen.php');
exit;
