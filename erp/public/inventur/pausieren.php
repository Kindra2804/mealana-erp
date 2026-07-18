<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/inventur/InventurService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/inventur/liste.php');
    exit;
}

$id     = (int)($_POST['id'] ?? 0);
$result = (new InventurService())->pausieren($id);

$_SESSION[$result['erfolg'] ? 'erfolg' : 'fehler'] = $result['erfolg'] ? 'Inventur pausiert.' : $result['fehler'];
header('Location: ' . BASE_PATH . '/inventur/liste.php');
