<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: export.php');
    exit;
}

$db = Database::getInstance();
$stmt = $db->prepare("UPDATE system_einstellungen SET wert = :w WHERE schluessel = :s");

// Zwei Formulare auf export.php (DATEV + Partner-Konten) → nur mitgeschickte Felder speichern
foreach (['datev_berater_nr', 'datev_mandant_nr', 'datev_wj_beginn', 'datev_sachkontenlaenge'] as $schluessel) {
    if (!isset($_POST[$schluessel])) continue;
    $stmt->execute([':w' => trim((string)$_POST[$schluessel]), ':s' => $schluessel]);
}

// Partner-Konten (Migration 209): leer = nicht buchen (Hinweis im Export), sonst Konto aus dem Kontenplan
$kontoDa = $db->prepare("SELECT 1 FROM kontenplan WHERE kontonummer = ? AND aktiv = 1");
foreach (['konto_partner_kommission', 'konto_partner_fremd', 'konto_partner_spende'] as $schluessel) {
    if (!isset($_POST[$schluessel])) continue;
    $wert = trim((string)$_POST[$schluessel]);
    if ($wert !== '') {
        $kontoDa->execute([$wert]);
        if (!$kontoDa->fetchColumn()) continue;
    }
    $stmt->execute([':w' => $wert, ':s' => $schluessel]);
}

$_SESSION['erfolg'] = 'Einstellungen gespeichert.';
header('Location: export.php');
exit;
