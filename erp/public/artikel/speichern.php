<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/core/Database.php';

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

$weRueckkehr = $data['we_rueckkehr'] ?? '';
$weEan       = $data['we_ean']       ?? '';

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
    'lieferzeit_text',
    'herkunftsland',
    'taric_code',
    'grundpreis_bezugsmenge',
    'grundpreis_anzeigen',
    'charge_pflicht',
    'aktiv',
    'ueberverkauf_erlaubt',
    'ist_auslaufartikel',
    'zustand',
    'zustand_vater_id'
]));

$artikelData['brutto_vk'] = $data['brutto_vk'] ?? null;
$artikelData['netto_vk']  = $data['netto_vk']  ?? null;
$artikelData['ean_gtin13'] = $data['ean_gtin13'] ?? null;

foreach (
    [
        'technische_details',
        'beschreibung_intern',
        'meta_titel',
        'meta_description',
        'url_slug',
        'kurzbeschreibung',
        'beschreibung',
        'gewicht_artikel',
        'gewicht_versand',
        'lieferzeit_text',
        'herkunftsland',
        'taric_code'
    ] as $feld
) {
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

// Artikelnummer-Fallback: ART-001, ART-002, ... wenn leer gelassen
if (empty($artikelData['artikelnummer'])) {
    $pdo = Database::getInstance();
    $row = $pdo->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(artikelnummer, 5) AS UNSIGNED)), 0) AS max_num
        FROM artikel
        WHERE artikelnummer REGEXP '^ART-[0-9]+$'
    ")->fetch();
    $naechste = (int)$row['max_num'] + 1;
    $artikelData['artikelnummer'] = 'ART-' . str_pad($naechste, 3, '0', STR_PAD_LEFT);
}

$service = new ArtikelService();
$result = $service->save($artikelData);

if ($result['erfolg']) {
    $neueArtikelId = $result['id'];
    $pdo = Database::getInstance();

    // Kategorien zuweisen — über saveKategorien() damit Aktionspreise geprüft werden
    $kategorieIds    = array_map('intval', array_filter((array)($data['kategorien'] ?? [])));
    $aktionsHinweise = $service->saveKategorien($neueArtikelId, $kategorieIds);
    if (!empty($aktionsHinweise)) {
        $_SESSION['aktionspreis_offen'] = [
            'artikel_id' => $neueArtikelId,
            'aktionen'   => $aktionsHinweise,
        ];
    }

    // Lieferant + EK (optional, aus neu.php)
    $lieferantId = (int)($data['lf_lieferant_id'] ?? 0);
    if ($lieferantId > 0) {
        $pdo->prepare("
            INSERT INTO artikel_lieferanten
                (artikel_id, lieferant_id, artikelnummer_lieferant, netto_ek, waehrung,
                 vpe_menge, lieferzeit_tage, mindestabnahme, standard_lieferant)
            VALUES (?, ?, ?, ?, ?, 1, 0, 0, 1)
        ")->execute([
            $neueArtikelId,
            $lieferantId,
            (string)($data['lf_artikelnummer'] ?? ''),
            (float)($data['lf_ek_netto'] ?? 0),
            in_array($data['lf_waehrung'] ?? '', ['EUR', 'USD', 'CHF'])
                ? $data['lf_waehrung']
                : 'EUR',
        ]);
    }

    $_SESSION['erfolg'] = 'Artikel wurde gespeichert!';

    if ($weRueckkehr && str_starts_with($weRueckkehr, '/mealana/')) {
        header('Location: ' . $weRueckkehr);
    } else {
        header('Location: detail.php?id=' . $neueArtikelId);
    }
    exit;
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $artikelData;
    if ($weRueckkehr) {
        $_SESSION['we_rueckkehr'] = $weRueckkehr;
        $_SESSION['we_ean']       = $weEan;
    }
    header('Location: neu.php');
    exit;
}
