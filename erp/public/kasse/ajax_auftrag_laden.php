<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db   = Database::getInstance();
$q    = trim($_GET['q'] ?? '');
$alle = ($_GET['alle'] ?? '0') === '1';

// Basis-Filter: nicht storniert. 'abgeschlossen' bleibt grundsätzlich erlaubt (siehe unten
// gezielt eingeschränkt) — ein bezahlter, versendeter Auftrag springt durch die Auto-Logik
// in packplatz/warenausgang/abschliessen.php sofort von 'versendet' auf 'abgeschlossen',
// der 'versendet'-Zustand ist für diesen (häufigsten) Fall praktisch nicht beobachtbar.
$basisFilter = "a.lieferstatus != 'storniert'";

if ($alle) {
    // Alle offenen (nicht abgeschlossenen) Aufträge (nicht Kassen-Bons)
    $where = $basisFilter . " AND a.kanal != 'kasse' AND a.lieferstatus != 'abgeschlossen'";
} else {
    // Abholung-Aufträge (jeder Status) ODER versendet/teilgeliefert/abgeschlossen
    // (Retouren-Kandidaten) unabhängig von der Lieferart — sonst findet das Personal
    // Rückgaben nicht ohne den "alle"-Umschalter.
    $where = $basisFilter . " AND a.kanal != 'kasse'
              AND (a.lieferart = 'abholung' OR a.lieferstatus IN ('versendet', 'teilgeliefert', 'abgeschlossen'))";
}

$params = [];
if ($q !== '') {
    $where .= " AND (a.auftrag_nr LIKE :q OR k.name LIKE :q OR k.email LIKE :q
                     OR CONCAT(
                         COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.kunden_snapshot, '$.vorname')),''),
                         ' ',
                         COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.kunden_snapshot, '$.nachname')),'')
                     ) LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

$stmt = $db->prepare("
    SELECT a.id, a.auftrag_nr, a.bruttobetrag, a.zahlungsstatus, a.lieferstatus,
           a.erstellt_am, a.kunden_snapshot, a.kunden_id
    FROM auftraege a
    LEFT JOIN kunden k ON k.id = a.kunden_id
    WHERE {$where}
    ORDER BY a.erstellt_am DESC
    LIMIT 50
");
$stmt->execute($params);
$auftraege = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Status-Labels
$lieferLabels = [
    'neu'             => 'Offen',
    'in_bearbeitung'  => 'In Bearb.',
    'versandbereit'   => 'Bereit',
    'teilgeliefert'   => 'Teillief.',
    'versendet'       => 'Versendet',
    'abgeschlossen'   => 'Abgeschl.',
];
$zahlLabels = [
    'offen'       => 'Unbezahlt',
    'teilbezahlt' => 'Teilbez.',
    'bezahlt'     => 'Bezahlt',
    'erstattet'   => 'Erstattet',
];

// Positionen je Auftrag laden
$result = [];
foreach ($auftraege as $a) {
    $snap      = json_decode($a['kunden_snapshot'] ?? '{}', true) ?: [];
    $kundenName = trim(($snap['vorname'] ?? '') . ' ' . ($snap['nachname'] ?? ''))
                ?: ($snap['firma'] ?? 'Laufkunde');

    // Positionen
    $pStmt = $db->prepare("
        SELECT p.id, p.artikel_id, p.bezeichnung, p.ean, p.charge,
               p.menge, p.menge_geliefert, p.menge_retourniert,
               p.einzelpreis_netto, p.steuer_prozent, p.rabatt_prozent
        FROM auftrag_positionen p
        WHERE p.auftrag_id = ?
        ORDER BY p.sort_order, p.id
    ");
    $pStmt->execute([$a['id']]);
    $positionen = [];
    foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $positionen[] = [
            'auftrag_position_id' => (int)$p['id'],
            'artikel_id'          => $p['artikel_id'] ? (int)$p['artikel_id'] : null,
            'bezeichnung'         => $p['bezeichnung'],
            'ean'                 => $p['ean'] ?? null,
            'charge'              => $p['charge'] ?? null,
            'menge'               => (float)$p['menge'],
            'menge_geliefert'     => (float)($p['menge_geliefert'] ?? 0),
            'menge_retourniert'   => (float)($p['menge_retourniert'] ?? 0),
            'einzelpreis_brutto'  => round((float)$p['einzelpreis_netto'] * (1 + (float)$p['steuer_prozent'] / 100), 4),
            'steuer_prozent'      => (float)$p['steuer_prozent'],
            'rabatt_prozent'      => (float)$p['rabatt_prozent'],
        ];
    }

    $result[] = [
        'id'                => (int)$a['id'],
        'auftrag_nr'        => $a['auftrag_nr'],
        'kunden_name'       => $kundenName,
        'bruttobetrag'      => (float)$a['bruttobetrag'],
        'zahlungsstatus'    => $a['zahlungsstatus'],
        'lieferstatus'      => $a['lieferstatus'],
        'zahlungsstatus_label' => $zahlLabels[$a['zahlungsstatus']] ?? $a['zahlungsstatus'],
        'lieferstatus_label'   => $lieferLabels[$a['lieferstatus']] ?? $a['lieferstatus'],
        'erstellt_datum'    => date('d.m.Y', strtotime($a['erstellt_am'])),
        'positionen'        => $positionen,
        'kunden_id'         => $a['kunden_id'] ? (int)$a['kunden_id'] : null,
    ];
}

echo json_encode($result);
