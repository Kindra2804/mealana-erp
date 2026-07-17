<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: export.php');
    exit;
}

$db = Database::getInstance();
$stmt = $db->prepare("UPDATE system_einstellungen SET wert = :w WHERE schluessel = :s");

foreach (['datev_berater_nr', 'datev_mandant_nr', 'datev_wj_beginn', 'datev_sachkontenlaenge'] as $schluessel) {
    $stmt->execute([':w' => trim((string)($_POST[$schluessel] ?? '')), ':s' => $schluessel]);
}

$_SESSION['erfolg'] = 'DATEV-Einstellungen gespeichert.';
header('Location: export.php');
exit;
