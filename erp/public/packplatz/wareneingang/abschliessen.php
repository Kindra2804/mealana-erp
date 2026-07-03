<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/modules/wareneingang/WareneingangService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$bestellungId     = (int)($_POST['bestellung_id'] ?? 0);
$aktion           = $_POST['aktion'] ?? 'warten';
$gutschriftBetrag = !empty($_POST['gutschrift_betrag']) ? (float)$_POST['gutschrift_betrag'] : null;
$gutschriftNotiz  = !empty($_POST['gutschrift_notiz'])  ? trim($_POST['gutschrift_notiz'])    : null;

$service = new WareneingangService();

if ($aktion === 'komplett' || $aktion === 'streichen') {
    $result = $service->abschliessenMitRest($bestellungId, $aktion, $gutschriftNotiz, $gutschriftBetrag);
    if ($result['erfolg']) {
        $_SESSION['erfolg'] = $aktion === 'streichen'
            ? 'Bestellung abgeschlossen — Rest gestrichen.'
            : 'Bestellung erfolgreich abgeschlossen.';
        // Zurück zur Wareneingang-Liste: Bestellung ist fertig
        header('Location: ' . BASE_PATH . '/packplatz/wareneingang/index.php');
        exit;
    }
}

// 'warten' oder Fehler → zurück zum Detail
header('Location: ' . BASE_PATH . '/packplatz/wareneingang/detail.php?bestellung_id=' . $bestellungId);
exit;
