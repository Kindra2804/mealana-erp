<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/Positionsrechnung.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragAbschluss.php';

header('Content-Type: application/json; charset=utf-8');

$db   = Database::getInstance();
$q    = trim($_GET['q'] ?? '');
$alle = ($_GET['alle'] ?? '0') === '1';

// Basis-Filter: nicht storniert. 'abgeschlossen' bleibt grundsätzlich erlaubt (siehe unten
// gezielt eingeschränkt) — ein bezahlter, versendeter Auftrag springt durch die Auto-Logik
// in packplatz/warenausgang/abschliessen.php sofort von 'versendet' auf 'abgeschlossen',
// der 'versendet'-Zustand ist für diesen (häufigsten) Fall praktisch nicht beobachtbar.
// Komplett zurückgegebene/erstattete Aufträge (keine Position mit Restmenge) bieten an der
// Kasse nichts mehr — sonst würden sie leer geladen (Jacky 2026-10-02)
$basisFilter = "a.lieferstatus != 'storniert'
                AND EXISTS (SELECT 1 FROM auftrag_positionen ap_rest
                            WHERE ap_rest.auftrag_id = a.id
                              AND ap_rest.menge > GREATEST(ap_rest.menge_retourniert, ap_rest.menge_gutgeschrieben))";

// Sammelabholung: weitere offene Abholungen desselben Kunden zu einem schon geladenen
// Auftrag (gleiche kunden_id, bei Gast-Bestellungen ohne Kundenkonto gleiche E-Mail).
// Nur Aufträge, die noch abgeholt werden können -- teilgeliefert/versendet laufen an der
// Kasse als Retoure und werden nicht mit einer Abholung gemischt.
$weitereZu = (int)($_GET['weitere_zu'] ?? 0);

if ($weitereZu) {
    $ref = $db->prepare("SELECT kunden_id, JSON_UNQUOTE(JSON_EXTRACT(kunden_snapshot, '$.email')) AS email FROM auftraege WHERE id = ?");
    $ref->execute([$weitereZu]);
    $refAuftrag = $ref->fetch(PDO::FETCH_ASSOC);
    if (!$refAuftrag || (!$refAuftrag['kunden_id'] && trim((string)$refAuftrag['email']) === '')) {
        echo json_encode([]);
        exit;
    }
    $where = $basisFilter . " AND a.kanal NOT IN ('kasse', 'jtl_archiv', 'haendler') AND a.lieferart = 'abholung'
              AND (a.lieferstatus IN ('neu', 'in_bearbeitung', 'kommissioniert', 'versandbereit', 'abholbereit', 'zurueckgestellt')
                   OR (a.lieferstatus = 'teilgeliefert' AND EXISTS (SELECT 1 FROM auftrag_positionen ap
                        WHERE ap.auftrag_id = a.id AND ap.menge > ap.menge_abgeholt)))
              AND a.id != :ref_id
              AND (a.kunden_id = :ref_kid
                   OR (:ref_email <> '' AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(a.kunden_snapshot, '$.email'))) = LOWER(:ref_email2)))";
    $params = [
        ':ref_id'     => $weitereZu,
        ':ref_kid'    => (int)$refAuftrag['kunden_id'],
        ':ref_email'  => trim((string)$refAuftrag['email']),
        ':ref_email2' => trim((string)$refAuftrag['email']),
    ];
    $q = '';
} elseif ($alle) {
    // Alle offenen (nicht abgeschlossenen) Aufträge (nicht Kassen-Bons, nicht Archiv)
    $where = $basisFilter . " AND a.kanal NOT IN ('kasse', 'jtl_archiv', 'haendler') AND a.lieferstatus != 'abgeschlossen'";
} else {
    // Abholung-Aufträge (jeder Status) ODER versendet/teilgeliefert/abgeschlossen
    // (Retouren-Kandidaten) unabhängig von der Lieferart — sonst findet das Personal
    // Rückgaben nicht ohne den "alle"-Umschalter. Archiv-Aufträge bewusst ausgeschlossen,
    // sonst tauchen die immer als "abgeschlossen" markierten JTL-Altaufträge hier auf.
    $where = $basisFilter . " AND a.kanal NOT IN ('kasse', 'jtl_archiv', 'haendler')
              AND (a.lieferart = 'abholung' OR a.lieferstatus IN ('versendet', 'teilgeliefert', 'abgeschlossen', 'retoure_offen'))";
}

$params = $params ?? [];
if ($q !== '') {
    // Kundendaten in `kunden` sind verschlüsselt (*_enc) -- gesucht wird im Klartext-
    // Snapshot am Auftrag (Name, Firma, E-Mail zum Bestellzeitpunkt)
    $where .= " AND (a.auftrag_nr LIKE :q
                     OR JSON_UNQUOTE(JSON_EXTRACT(a.kunden_snapshot, '$.email')) LIKE :q
                     OR JSON_UNQUOTE(JSON_EXTRACT(a.kunden_snapshot, '$.name')) LIKE :q
                     OR JSON_UNQUOTE(JSON_EXTRACT(a.kunden_snapshot, '$.firma')) LIKE :q
                     OR CONCAT(
                         COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.kunden_snapshot, '$.vorname')),''),
                         ' ',
                         COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.kunden_snapshot, '$.nachname')),'')
                     ) LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

$stmt = $db->prepare("
    SELECT a.id, a.auftrag_nr, a.bruttobetrag, a.zahlungsstatus, a.lieferstatus, a.lieferart,
           a.erstellt_am, a.kunden_snapshot, a.kunden_id
    FROM auftraege a
    WHERE {$where}
    ORDER BY " . ($weitereZu ? "a.erstellt_am ASC" : "a.erstellt_am DESC") . "
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
    'abholbereit'     => 'Abholbereit',
    'kommissioniert'  => 'Gepackt',
    'zurueckgestellt' => 'Zurückgest.',
    'abgeschlossen'   => 'Abgeschl.',
    'retoure_offen'   => 'Retoure offen',
];
$zahlLabels = [
    'offen'       => 'Unbezahlt',
    'ausstehend'  => 'Unbezahlt',
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
               (SELECT artikelnummer FROM artikel WHERE id = p.artikel_id) AS artikelnummer,
               (SELECT charge_pflicht FROM artikel WHERE id = p.artikel_id) AS charge_pflicht,
               p.menge, p.menge_geliefert, p.menge_abgeholt, GREATEST(p.menge_retourniert, p.menge_gutgeschrieben) AS menge_retourniert,
               p.einzelpreis_netto, p.steuer_prozent, p.rabatt_prozent
        FROM auftrag_positionen p
        WHERE p.auftrag_id = ?
        ORDER BY p.sort_order, p.id
    ");
    $pStmt->execute([$a['id']]);
    $positionen = [];
    $fachSumme  = 0.0; // im Abholfach (gepackt, noch nicht abgeholt) — siehe bon_speichern.php $fachVon
    $offenSumme = 0.0; // noch nicht abgeholt (Fach + ungepackt)
    foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $geliefertFach = (float)$p['menge_geliefert'];
        if ($a['lieferstatus'] === 'abholbereit' && $geliefertFach < 0.001) $geliefertFach = (float)$p['menge'];
        $imFach = max(0.0, $geliefertFach - (float)$p['menge_abgeholt']);
        $offen  = max(0.0, (float)$p['menge'] - (float)$p['menge_abgeholt']);
        $fachSumme  += $imFach;
        $offenSumme += $offen;
        $positionen[] = [
            'menge_im_fach'       => $imFach,
            'menge_offen'         => $offen,
            'auftrag_position_id' => (int)$p['id'],
            'artikel_id'          => $p['artikel_id'] ? (int)$p['artikel_id'] : null,
            'bezeichnung'         => $p['bezeichnung'],
            'ean'                 => $p['ean'] ?? null,
            // für die Chargen-Abfrage, wenn die Ware an der Kasse mitgenommen wird
            'artikelnummer'       => $p['artikelnummer'] ?? null,
            'charge_pflicht'      => (int)($p['charge_pflicht'] ?? 0),
            'charge'              => $p['charge'] ?? null,
            'menge'               => (float)$p['menge'],
            'menge_geliefert'     => (float)($p['menge_geliefert'] ?? 0),
            // inkl. bereits gutgeschriebener Menge (Packplatz-Retoure/ERP-Gutschrift), damit die
            // Kasse sie nicht nochmal als Retoure anbietet
            'menge_retourniert'   => (float)($p['menge_retourniert'] ?? 0),
            'einzelpreis_brutto'  => Positionsrechnung::einzelBrutto((float)$p['einzelpreis_netto'], (float)$p['steuer_prozent']),
            'steuer_prozent'      => (float)$p['steuer_prozent'],
            'rabatt_prozent'      => (float)$p['rabatt_prozent'],
        ];
    }

    // Guthaben = mehr bezahlt als der (geänderte) Auftragsbetrag, z.B. nach Umstellung
    // Versand → Abholung (Versandkosten entfallen). Die Kasse zahlt es bei der Abholung aus.
    $gh = $db->prepare("
        SELECT (SELECT COALESCE(SUM(betrag), 0) FROM auftrag_zahlungen WHERE auftrag_id = a.id)
             + a.gutschein_betrag - a.bruttobetrag
             - (SELECT COALESCE(SUM(gebuehr), 0) FROM mahnungen WHERE auftrag_id = a.id AND status = 'versendet' AND gebuehr_erlassen_am IS NULL)
        FROM auftraege a WHERE a.id = ?
    ");
    $gh->execute([(int)$a['id']]);
    $guthaben = $a['zahlungsstatus'] === 'bezahlt' ? max(0.0, round((float)$gh->fetchColumn(), 2)) : 0.0;
    // Mit Belegen (Rechnung/Korrektur) zählt deren Saldo -- z.B. Stornorechnung inkl. Versand
    if ($a['zahlungsstatus'] === 'bezahlt' && AuftragAbschluss::saldoAussagekraeftig((int)$a['id'])) {
        $guthaben = max(0.0, round(-AuftragAbschluss::saldo((int)$a['id']), 2));
    }

    $result[] = [
        'id'                => (int)$a['id'],
        'guthaben'          => $guthaben,
        'auftrag_nr'        => $a['auftrag_nr'],
        'kunden_name'       => $kundenName,
        'bruttobetrag'      => (float)$a['bruttobetrag'],
        'zahlungsstatus'    => $a['zahlungsstatus'],
        'lieferstatus'      => $a['lieferstatus'],
        'lieferart'         => $a['lieferart'],
        // Abholung, von der schon ein Teil übergeben wurde: Rest im Fach bzw. noch offen
        'menge_im_fach'     => $fachSumme,
        'menge_offen'       => $offenSumme,
        'zahlungsstatus_label' => $zahlLabels[$a['zahlungsstatus']] ?? $a['zahlungsstatus'],
        'lieferstatus_label'   => $lieferLabels[$a['lieferstatus']] ?? $a['lieferstatus'],
        'erstellt_datum'    => date('d.m.Y', strtotime($a['erstellt_am'])),
        'positionen'        => $positionen,
        'kunden_id'         => $a['kunden_id'] ? (int)$a['kunden_id'] : null,
        'kunden_email'      => strtolower(trim($snap['email'] ?? '')) ?: null,
    ];
}

echo json_encode($result);
