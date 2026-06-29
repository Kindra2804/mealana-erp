<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db   = Database::getInstance();
$q    = trim($_GET['q'] ?? '');
$alle = ($_GET['alle'] ?? '0') === '1';

// Basis-Filter: nicht storniert, nicht abgeschlossen
$basisFilter = "a.lieferstatus NOT IN ('abgeschlossen','storniert')";

if ($alle) {
    // Alle offenen Aufträge (nicht Kassen-Bons)
    $where = $basisFilter . " AND a.kanal != 'kasse'";
} else {
    // Nur Abholung-Aufträge die bereit sind (versandbereit = "Fertig zur Abholung" gemeldet)
    // ODER noch offen/in_bearbeitung (für Aufträge die noch nicht am Packplatz waren)
    $where = $basisFilter . " AND a.lieferart = 'abholung' AND a.kanal != 'kasse'";
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
        SELECT p.id, p.artikel_id, p.bezeichnung, p.ean,
               p.menge, p.menge_geliefert,
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
            'menge'               => (float)$p['menge'],
            'menge_geliefert'     => (float)($p['menge_geliefert'] ?? 0),
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
