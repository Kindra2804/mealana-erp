<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/core/Logger.php';

$input  = json_decode(file_get_contents('php://input'), true);
$ids    = $input['ids']    ?? [];
$aktion = $input['aktion'] ?? '';

if (empty($ids) || $aktion === '') {
    echo json_encode(['fehler' => 'Aktion oder IDs fehlen']);
    exit;
}

$ids     = array_map('intval', $ids);
$service = new ArtikelService();

if ($aktion === 'aktivieren') {
    foreach ($ids as $id) {
        $service->aktivieren($id);
    }
    Logger::log('artikel.masse.aktivieren', 'artikel', 0, ['ids' => $ids]);
    $_SESSION['erfolg'] = count($ids) . ' Artikel wurden aktiviert.';
    echo json_encode(['erfolg' => true]);
    exit;
}

if ($aktion === 'deaktivieren') {
    foreach ($ids as $id) {
        $service->delete($id);
    }
    Logger::log('artikel.masse.deaktivieren', 'artikel', 0, ['ids' => $ids]);
    $_SESSION['erfolg'] = count($ids) . ' Artikel wurden deaktiviert.';
    echo json_encode(['erfolg' => true]);
    exit;
}

if ($aktion === 'ist_auslaufartikel') {
    foreach ($ids as $id) {
        $service->auslaufSetzen($id);
    }
    Logger::log('artikel.masse.auslauf_setzen', 'artikel', 0, ['ids' => $ids]);
    $_SESSION['erfolg'] = count($ids) . ' Artikel als Auslaufartikel markiert.';
    echo json_encode(['erfolg' => true]);
    exit;
}

if ($aktion === 'kein_auslaufartikel') {
    foreach ($ids as $id) {
        $service->auslaufEntfernen($id);
    }
    Logger::log('artikel.masse.auslauf_entfernen', 'artikel', 0, ['ids' => $ids]);
    $_SESSION['erfolg'] = count($ids) . ' Artikel: Auslauf-Flag entfernt.';
    echo json_encode(['erfolg' => true]);
    exit;
}

echo json_encode(['fehler' => 'Unbekannte Aktion']);
exit;
