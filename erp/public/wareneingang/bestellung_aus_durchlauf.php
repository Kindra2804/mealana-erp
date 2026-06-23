<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';
require_once __DIR__ . '/../../src/modules/wareneingang/WareneingangService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mealana/wareneingang/index.php');
    exit;
}

$durchlauf   = $_SESSION['we_durchlauf'] ?? [];
$lieferantId = (int)($_POST['lieferant_id'] ?? 0);
$lagerId     = (int)($_POST['lager_id'] ?? 1);

if (empty($durchlauf) || !$lieferantId) {
    $_SESSION['fehler_we'] = 'Lieferant und mindestens ein Artikel sind erforderlich.';
    header('Location: /mealana/wareneingang/index.php');
    exit;
}

$bestellService = new BestellungService();
$weService      = new WareneingangService();

// Schritt 1: Bestellung anlegen (Status = offen)
$positionen = array_map(fn($item) => [
    'artikel_id'      => $item['artikel_id'],
    'menge_bestellt'  => $item['menge'],
    'ek_preis'        => null,
    'lieferzeit_text' => null,
], $durchlauf);

$result = $bestellService->anlegen([
    'lieferant_id'    => $lieferantId,
    'bestelldatum'    => date('Y-m-d'),
    'zahlungsart'     => null,
    'erwartet_am'     => null,
    'lieferzeit_text' => null,
    'ab_nummer'       => null,
    'notiz'           => 'Retroaktiv aus Wareneingang erstellt',
], $positionen);

if (!$result['erfolg']) {
    $_SESSION['fehler_we'] = implode(', ', $result['fehler']);
    header('Location: /mealana/wareneingang/index.php');
    exit;
}

$bestellungId = $result['id'];

// Schritt 2: Alle Positionen laden und Lagerbestand buchen
// (bucheMenge setzt automatisch Status auf 'erledigt' wenn alle Positionen durch sind)
$allePositionen = $bestellService->getPositionen($bestellungId);

foreach ($allePositionen as $pos) {
    $weService->bucheMenge([
        'position_id' => $pos['id'],
        'artikel_id'  => $pos['artikel_id'],
        'lager_id'    => $lagerId,
        'menge'       => $pos['menge_bestellt'],
        'charge'      => null,
    ]);
}

// Schritt 3: Session aufräumen
unset($_SESSION['we_durchlauf']);

$_SESSION['erfolg'] = 'Retroaktive Bestellung angelegt und Lager gebucht.';
header('Location: /mealana/bestellungen/detail.php?id=' . $bestellungId);
exit;
