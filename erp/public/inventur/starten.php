<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/inventur/InventurService.php';
require_once __DIR__ . '/../../src/modules/inventur/InventurRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/inventur/neu.php');
    exit;
}

$scopeTabelle = $_POST['scope_tabelle'] ?? '';
$scopeId      = 0;
if (in_array($scopeTabelle, InventurRepository::gueltigeScopeTabellen(), true)) {
    $scopeId = (int)($_POST['scope_id_' . $scopeTabelle] ?? 0);
}

$service = new InventurService();
$result  = $service->starten([
    'scope_tabelle' => $scopeTabelle,
    'scope_id'      => $scopeId,
    'blind_modus'   => $_POST['blind_modus'] ?? '',
    'notiz'         => $_POST['notiz'] ?? '',
]);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Inventur gestartet.';
    header('Location: ' . BASE_PATH . '/inventur/liste.php');
    exit;
}

$_SESSION['fehler'] = $result['fehler'];
header('Location: ' . BASE_PATH . '/inventur/neu.php');
