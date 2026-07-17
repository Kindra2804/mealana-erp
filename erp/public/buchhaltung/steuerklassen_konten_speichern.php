<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: steuerklassen_konten.php');
    exit;
}

$db = Database::getInstance();

$stmt = $db->prepare("UPDATE steuerklassen_konten SET steuer_konto_id = :k WHERE id = :id");
foreach (($_POST['konto_id'] ?? []) as $id => $kontoId) {
    $stmt->execute([
        ':k'  => $kontoId !== '' ? (int)$kontoId : null,
        ':id' => (int)$id,
    ]);
}

$_SESSION['erfolg'] = 'Steuerklassen-Konten gespeichert.';
header('Location: steuerklassen_konten.php');
exit;
