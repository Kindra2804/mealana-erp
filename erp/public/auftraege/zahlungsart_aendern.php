<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$id          = (int)($_POST['id'] ?? 0);
$zahlungsart = $_POST['zahlungsart'] ?? '';
$erlaubt     = ['vorkasse', 'paypal', 'rechnung', 'bar', 'nachnahme', 'gutschein', 'gemischt'];

if (!$id || !in_array($zahlungsart, $erlaubt, true)) {
    $_SESSION['fehler'] = 'Ungültige Anfrage.';
    header("Location: detail.php?id=$id");
    exit;
}

$db = Database::getInstance();

$auftrag = $db->prepare("SELECT id, zahlungsart FROM auftraege WHERE id = ?");
$auftrag->execute([$id]);
$auftrag = $auftrag->fetch(PDO::FETCH_ASSOC);

if (!$auftrag) {
    $_SESSION['fehler'] = 'Auftrag nicht gefunden.';
    header("Location: liste.php");
    exit;
}

$db->prepare("UPDATE auftraege SET zahlungsart = ? WHERE id = ?")->execute([$zahlungsart, $id]);

Logger::log('auftrag.zahlungsart_geaendert', 'auftraege', $id, [
    'von' => $auftrag['zahlungsart'],
    'auf' => $zahlungsart,
]);

$_SESSION['erfolg'] = 'Zahlungsart auf ' . ucfirst($zahlungsart) . ' umgestellt.';
header("Location: detail.php?id=$id");
exit;
