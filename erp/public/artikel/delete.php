<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$id = (int) ($_GET['id'] ?? 0);
$service = new ArtikelService();

$result = $service->delete($id);

if ($result['erfolg'] === true) {
    $_SESSION['erfolg'] = 'Artikel wurde deaktiviert.';
} else {
    $_SESSION['fehler'] = $result['fehler'];
}

header('Location: liste.php');
exit;
