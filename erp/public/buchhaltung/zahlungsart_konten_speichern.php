<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: zahlungsart_konten.php');
    exit;
}

$db = Database::getInstance();

$kontoIds = $_POST['konto_id'] ?? [];
$hinweise = $_POST['hinweis'] ?? [];

$stmt = $db->prepare("UPDATE zahlungsart_konten SET konto_id = :k, hinweis = :h WHERE id = :id");
foreach ($kontoIds as $id => $kontoId) {
    $stmt->execute([
        ':k'  => $kontoId !== '' ? (int)$kontoId : null,
        ':h'  => trim((string)($hinweise[$id] ?? '')) ?: null,
        ':id' => (int)$id,
    ]);
}

$_SESSION['erfolg'] = 'Zahlungsart-Konten gespeichert.';
header('Location: zahlungsart_konten.php');
exit;
