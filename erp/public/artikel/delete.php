<?php
session_start();
require_once __DIR__ . '/../../src/modules/artikel/ArtikelController.php';

$id = (int) ($_GET['id'] ?? 0);
$controller = new ArtikelController();

if ($controller->deactivate($id)) {
    $_SESSION['erfolg'] = 'Artikel wurde deaktiviert.';
}

header('Location: liste.php');
exit;
