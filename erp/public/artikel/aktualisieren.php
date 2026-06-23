<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bearbeiten.php');
    exit;
}

$data        = $_POST;
$weRueckkehr = $data['we_rueckkehr'] ?? '';

if (!isset($data['charge_pflicht']) || $data['charge_pflicht'] != '1') {
    $data['charge_pflicht'] = '0';
}

if (!isset($data['ist_auslaufartikel']) || $data['ist_auslaufartikel'] != '1') {
    $data['ist_auslaufartikel'] = '0';
}

if (!isset($data['ueberverkauf_erlaubt']) || $data['ueberverkauf_erlaubt'] != '1') {
    $data['ueberverkauf_erlaubt'] = '0';
}

if (!isset($data['aktiv']) || $data['aktiv'] != '1') {
    $data['aktiv'] = '0';
}

if (!isset($data['grundpreis_anzeigen']) || $data['grundpreis_anzeigen'] != '1') {
    $data['grundpreis_anzeigen'] = '0';
}

// aktualisieren.php muss diese Felder herausfiltern:
$artikelData = array_intersect_key($data, array_flip([
    'id',
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
    'laenge',
    'breite',
    'hoehe',
    'herkunftsland',
    'taric_code',
    'grundpreis_bezugsmenge',
    'grundpreis_anzeigen',
    'charge_pflicht',
    'ist_auslaufartikel',
    'ueberverkauf_erlaubt',
    'aktiv',
    'zustand',
    'zustand_vater_id'
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
$result = $service->update($artikelData);

// Kategorien aktualisieren
$kategorieIds    = array_map('intval', $data['kategorien'] ?? []);
$aktionsHinweise = $service->saveKategorien((int)$artikelData['id'], $kategorieIds);

if ($result['erfolg']) {
    if (!empty($aktionsHinweise)) {
        $_SESSION['aktionspreis_offen'] = [
            'artikel_id' => (int)$artikelData['id'],
            'aktionen'   => $aktionsHinweise,
        ];
    }
    $_SESSION['erfolg'] = 'Artikel wurde aktualisiert!';
    if ($weRueckkehr && str_starts_with($weRueckkehr, '/mealana/')) {
        header('Location: ' . $weRueckkehr);
    } else {
        header('Location: detail.php?id=' . $data['id']);
    }
    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $artikelData;
    header('Location: detail.php?id=' . $data['id']);
    exit;
}
