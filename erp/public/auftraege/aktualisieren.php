<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

$service = new AuftragService();

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

// Kunden-Wechsel nur wenn Formular kunden_id-Feld gesendet hat (bei Rechnungs-Lock nicht vorhanden)
$kundenWechsel = isset($_POST['kunden_id']);
$neueKundenId  = !empty($_POST['kunden_id']) ? (int)$_POST['kunden_id'] : null;
$kundenSnapshot = null;
if ($kundenWechsel && $neueKundenId) {
    $ks   = new KundenService();
    $kund = $ks->getById($neueKundenId);
    if ($kund) {
        $anzeigeName    = trim(($kund['vorname'] ?? '') . ' ' . ($kund['nachname'] ?? ''));
        if ($kund['ist_firma'] && !empty($kund['firmenname'])) $anzeigeName = $kund['firmenname'];
        $kundenSnapshot = [
            'name'  => $anzeigeName ?: ('Kd. ' . $kund['kundennummer']),
            'email' => $kund['email'] ?? '',
        ];
    }
}

$data = [
    'zahlungsart'   => $_POST['zahlungsart']            ?? 'vorkasse',
    'lieferart' => !empty($_POST['lieferart'])  ? trim($_POST['lieferart']) : 'versand',
    'versandklasse_id' => !empty($_POST['versandklasse_id'])  ? (int)$_POST['versandklasse_id'] : NULL,
    'versandkosten' => !empty($_POST['versandkosten'])  ? (float)$_POST['versandkosten'] : 0.00,
    'notiz_intern'  => !empty($_POST['notiz_intern'])   ? trim($_POST['notiz_intern'])  : null,
    'notiz_versand' => !empty($_POST['notiz_versand'])  ? trim($_POST['notiz_versand']) : null,
];

// Kunden-Daten nur übergeben wenn Formular das Feld hatte (kein Rechnungs-Lock)
if ($kundenWechsel) {
    $data['kunden_id']       = $neueKundenId;
    $data['kunden_snapshot'] = $kundenSnapshot;
}

// Adressen nur übergeben wenn Formular die Felder hatte (kein Rechnungs-Lock)
if (isset($_POST['lieferadresse'])) {
    $lieferAdresseFelder = array_map('trim', $_POST['lieferadresse']);
    $data['lieferadresse_snapshot'] = array_filter($lieferAdresseFelder) ? $lieferAdresseFelder : null;
}
if (isset($_POST['rechnungsadresse'])) {
    $rechnungsAdresseFelder = array_map('trim', $_POST['rechnungsadresse']);
    $data['rechnungsadresse_snapshot'] = array_filter($rechnungsAdresseFelder) ? $rechnungsAdresseFelder : null;
}

$positionen = $_POST['positionen'] ?? [];

$ergebnis = $service->bearbeiten($id, $data, $positionen);

if (!$ergebnis['erfolg']) {
    $_SESSION['fehler']   = $ergebnis['fehler'];
    $_SESSION['formdata'] = $_POST;
    header('Location: /mealana/auftraege/bearbeiten.php?id=' . $id);
    exit;
}

$_SESSION['erfolg'] = 'Auftrag wurde aktualisiert.';
header('Location: /mealana/auftraege/detail.php?id=' . $ergebnis['id']);
exit;
