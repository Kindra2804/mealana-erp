<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/gutscheine/GutscheinService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

$service = new GutscheinService();
try {
    $ergebnis = $service->versende($id);
    $_SESSION['erfolg'] = !empty($ergebnis['versendet'])
        ? 'Gutschein wurde per Mail versendet.'
        : 'Keine E-Mail-Adresse hinterlegt -- nichts verschickt.';
} catch (Throwable $e) {
    $_SESSION['fehler'] = ['Versand fehlgeschlagen: ' . $e->getMessage()];
}

header('Location: detail.php?id=' . $id);
exit;
