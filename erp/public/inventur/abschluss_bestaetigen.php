<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/inventur/InventurService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/inventur/liste.php');
    exit;
}

$id     = (int)($_POST['id'] ?? 0);
$result = (new InventurService())->abschliessen($id);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Inventur abgeschlossen — ' . count($result['korrigiert']) . ' Artikel korrigiert, '
        . count($result['unveraendert']) . ' ohne Abweichung (nur Inventurdatum gesetzt).';
    header('Location: ' . BASE_PATH . '/inventur/liste.php');
    exit;
}

$_SESSION['fehler'] = $result['fehler'];
header('Location: ' . BASE_PATH . '/inventur/abschluss_vorschau.php?lauf_id=' . $id);
