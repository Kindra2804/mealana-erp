<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: neu.php');
    exit;
}

$data = $_POST;
if (!isset($data['charge_pflicht']) || $data['charge_pflicht'] != '1') {
    $data['charge_pflicht'] = '0';
}

if (!isset($data['ueberverkauf_erlaubt']) || $data['ueberverkauf_erlaubt'] != '1') {
    $data['ueberverkauf_erlaubt'] = '0';
}

// speichern.php muss diese Felder herausfiltern:
$artikelData = array_intersect_key($data, array_flip([
    'artikelnummer',
    'hersteller_id',
    'steuerklasse_id',
    'artikeltyp',
    'name',
    'kurzbeschreibung',
    'beschreibung',
    'technische_details',
    'beschreibung_intern',
    'meta_titel',
    'meta_description',
    'url_slug',
    'einheit_id',
    'inhalt_menge',
    'inhalt_einheit',
    'gewicht_artikel',
    'gewicht_versand',
    'herkunftsland',
    'taric_code',
    'grundpreis_bezugsmenge',
    'grundpreis_anzeigen',
    'charge_pflicht',
    'aktiv',
    'ueberverkauf_erlaubt',
    'ist_auslaufartikel'
]));

$artikelData['brutto_vk'] = $data['brutto_vk'] ?? null;
$artikelData['netto_vk']  = $data['netto_vk']  ?? null;
$artikelData['ean_gtin13'] = $data['ean_gtin13'] ?? null;

foreach (['technische_details', 'beschreibung_intern', 'meta_titel', 'meta_description', 'url_slug'] as $feld) {
    if (!array_key_exists($feld, $artikelData)) {
        $artikelData[$feld] = null;
    }
}

// Leere Strings zu NULL konvertieren
foreach ($artikelData as $key => $value) {
    if ($value === '') {
        $artikelData[$key] = null;
    }
}

$service = new ArtikelService();
$result = $service->save($artikelData);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Artikel wurde gespeichert!';
    header('Location: detail.php?id=' . $result['id']);
    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $artikelData;
    header('Location: neu.php');
    exit;
}
