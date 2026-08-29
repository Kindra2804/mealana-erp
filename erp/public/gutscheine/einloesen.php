<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/gutscheine/GutscheinRepository.php';
require_once __DIR__ . '/../../src/modules/gutscheine/GutscheinService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

$id     = (int)($_POST['id'] ?? 0);
$betrag = (float)($_POST['betrag'] ?? 0);

$repo = new GutscheinRepository();
$gutschein = $repo->findById($id);
if (!$gutschein) {
    header('Location: liste.php');
    exit;
}

$service = new GutscheinService();
$ergebnis = $service->einloesen($gutschein['code'], $betrag, 'erp', null, null, (int)$_SESSION['benutzer']['id']);

if (!$ergebnis['erfolg']) {
    $_SESSION['fehler'] = $ergebnis['fehler'];
} elseif (!empty($ergebnis['neuer_code'])) {
    $_SESSION['erfolg'] = 'Eingelöst. Für den Restbetrag von '
        . number_format($ergebnis['restguthaben'], 2, ',', '.')
        . ' € wurde der neue Code ' . $ergebnis['neuer_code'] . ' erzeugt.';
} else {
    $_SESSION['erfolg'] = 'Gutschein vollständig eingelöst.';
}

header('Location: detail.php?id=' . $id);
exit;
