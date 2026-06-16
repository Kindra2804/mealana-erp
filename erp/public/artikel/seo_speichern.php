<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$pdo = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Methode']);
    exit;
}

$artikel_id = (int) ($_POST['id'] ?? 0);
$meta_titel = $_POST['meta_titel'] ?? null;
$meta_description = $_POST['meta_description'] ?? null;
$url_slug = $_POST['url_slug'] ?? null;

if ($artikel_id > 0) {
    // UPDATE — bestehende Zeile
    $stmt = $pdo->prepare("
    UPDATE artikel SET
        meta_titel = :meta_titel,
        meta_description = :meta_description,
        url_slug = :url_slug
    WHERE id = :artikel_id
");

    $stmt->execute([
        'artikel_id' => $artikel_id,
        'meta_titel' => $meta_titel,
        'meta_description' => $meta_description,
        'url_slug' => $url_slug
    ]);

    $_SESSION['erfolg'] = 'SEO-Daten gespeichert!';
    header('Location: detail.php?id=' . $artikel_id);
    exit;
}

$_SESSION['fehler'] = 'Fehler beim speichern!';
header('Location: detail.php?id=' . $artikel_id);
exit;
