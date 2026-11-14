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
// Einzelartikel ohne angeklickten Treffer (z.B. Browser-Vorschlag gewählt oder Enter gedrückt):
// genau passende Artikelnummer bzw. eindeutigen Namen aus dem Suchfeld nehmen (Fund Jacky 2026-11-14)
if ($scopeTabelle === 'artikel' && !$scopeId && trim($_POST['artikel_suche'] ?? '') !== '') {
    $text = trim($_POST['artikel_suche']);
    $st = Database::getInstance()->prepare("SELECT id FROM artikel WHERE artikelnummer = ? OR name = ? LIMIT 2");
    $st->execute([$text, $text]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    if (count($ids) === 1) $scopeId = (int)$ids[0];
}

$service = new InventurService();
$result  = $service->starten([
    'scope_tabelle' => $scopeTabelle,
    'scope_id'      => $scopeId,
    'lager_id'      => (int)($_POST['lager_id_artikel'] ?? 0) ?: null,
    'blind_modus'   => $_POST['blind_modus'] ?? '',
    'notiz'         => $_POST['notiz'] ?? '',
]);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Inventur gestartet.';
    header('Location: ' . BASE_PATH . '/inventur/liste.php');
    exit;
}

$_SESSION['fehler'] = $result['fehler'];
$_SESSION['inventur_formular'] = $_POST;   // Auswahl beim Zurückspringen behalten
header('Location: ' . BASE_PATH . '/inventur/neu.php');
