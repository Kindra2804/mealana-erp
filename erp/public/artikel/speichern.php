<?php
session_start();
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: neu.php');
    exit;
}

$data = $_POST;

// speichern.php muss diese Felder herausfiltern:
$artikelData = array_intersect_key($data, array_flip([
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
    'aktiv'
]));

// Und Preise separat merken:
$brutto_vk = $data['brutto_vk'] ?? null;
$netto_vk = $data['netto_vk'] ?? null;

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
