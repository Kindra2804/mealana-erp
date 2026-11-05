<?php
// Widerruf aus dem Shop im ERP entscheiden: bestätigen / ablehnen (WiderrufService, Plugin NAHTLOS Connector ≥ 1.5.0)
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/shop/WiderrufService.php';

$auftragId = (int)($_POST['auftrag_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$auftragId) {
    header('Location: liste.php');
    exit;
}

$service    = new WiderrufService();
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

$erg = match ($_POST['aktion'] ?? '') {
    'bestaetigen' => $service->bestaetigen($auftragId, $benutzerId),
    'ablehnen'    => $service->ablehnen($auftragId, (string)($_POST['grund'] ?? ''), $benutzerId),
    default       => ['erfolg' => false, 'fehler' => 'Unbekannte Aktion.'],
};

if ($erg['erfolg']) {
    $_SESSION['erfolg'] = $erg['text'];
} else {
    $_SESSION['fehler'] = [$erg['fehler']];
}
header('Location: detail.php?id=' . $auftragId);
exit;
