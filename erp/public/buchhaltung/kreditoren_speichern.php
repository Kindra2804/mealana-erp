<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/buchhaltung/kreditoren.php');
    exit;
}

$repo = new LieferantenRepository();

foreach (($_POST['kreditorennummer'] ?? []) as $lieferantId => $nummer) {
    $repo->updateKreditorennummer((int)$lieferantId, trim((string)$nummer) ?: null);
}

$_SESSION['erfolg'] = 'Kreditorenkonten gespeichert.';
header('Location: ' . BASE_PATH . '/buchhaltung/kreditoren.php');
