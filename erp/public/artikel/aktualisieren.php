<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bearbeiten.php');
    exit;
}

$data = $_POST;

if (!isset($data['charge_pflicht']) || $data['charge_pflicht'] != '1') {
    $data['charge_pflicht'] = '0';
}

// aktualisieren.php muss diese Felder herausfiltern:
$artikelData = array_intersect_key($data, array_flip([
    'id',
    'artikelnummer',
    'hersteller_id',
    'steuerklasse_id',
    'artikeltyp',
    'name',
    'beschreibung_kurz',
    'beschreibung_lang',
    'einheit',
    'inhalt_menge',
    'inhalt_einheit',
    'gewicht_artikel',
    'gewicht_versand',
    'herkunftsland',
    'taric_code',
    'varianten_darstellung',
    'grundpreis_bezugsmenge',
    'grundpreis_anzeigen',
    'charge_pflicht',
    'aktiv'
]));

$artikelData['brutto_vk'] = $data['brutto_vk'] ?? null;
$artikelData['netto_vk']  = $data['netto_vk']  ?? null;
$artikelData['ean_gtin13'] = $data['ean_gtin13'] ?? null;

// Leere Strings zu NULL konvertieren
foreach ($artikelData as $key => $value) {
    if ($value === '') {
        $artikelData[$key] = null;
    }
}

$service = new ArtikelService();
$result = $service->update($artikelData);

// Kategorien aktualisieren
$kategorieIds = array_map('intval', $data['kategorien'] ?? []);
$service->saveKategorien((int)$artikelData['id'], $kategorieIds);


if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Artikel wurde aktualisiert!';
    header('Location: detail.php?id=' . $data['id']);
    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $artikelData;
    header('Location: bearbeiten.php?id=' . $data['id']);
    exit;
}
