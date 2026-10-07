<?php
/**
 * Kasse → "Rechnung bezahlen" (Zahlbeleg): offener Betrag zu einer Auftrags- oder
 * Rechnungsnummer (auch Teilnummer / per Scan) oder einem Kundennamen. Liefert nur
 * Aufträge, die bereits verrechnet sind (Rechnung vorhanden) und noch offen sind.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/dokumente/DokumentService.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$q  = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 3) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Bitte Auftrags- oder Rechnungsnummer eingeben.']);
    exit;
}

$stmt = $db->prepare("
    SELECT DISTINCT a.id, a.auftrag_nr, a.kunden_id, a.kunden_snapshot, a.zahlungsstatus
    FROM auftraege a
    JOIN rechnungen r ON r.auftrag_id = a.id
    WHERE a.lieferstatus <> 'storniert'
      AND (a.auftrag_nr LIKE :like1 OR r.rechnung_nr LIKE :like2 OR a.kunden_snapshot LIKE :like3)
    ORDER BY a.id DESC
    LIMIT 15
");
$like = '%' . $q . '%';
$stmt->execute([':like1' => $like, ':like2' => $like, ':like3' => $like]);

$dok = new DokumentService();
$treffer = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
    $offen = $dok->offenerRechnungsbetrag((int)$a['id']);
    $re = $db->prepare("SELECT rechnung_nr, bruttobetrag, DATE_FORMAT(erstellt_am, '%d.%m.%Y') AS datum FROM rechnungen WHERE auftrag_id = ? AND storniert = 0 ORDER BY id");
    $re->execute([(int)$a['id']]);
    $k = json_decode($a['kunden_snapshot'] ?? '{}', true) ?: [];
    $treffer[] = [
        'auftrag_id' => (int)$a['id'],
        'auftrag_nr' => $a['auftrag_nr'],
        'kunden_id'  => $a['kunden_id'] ? (int)$a['kunden_id'] : null,
        'kunde'      => trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')) ?: ($k['firma'] ?? ($k['name'] ?? '')),
        'rechnungen' => $re->fetchAll(PDO::FETCH_ASSOC),
        'offen'      => max(0, $offen),
    ];
}

// Noch nicht verrechnete, unbezahlte Aufträge (z.B. Zahlart "Rechnung" + Abholung, Ware noch im
// Haus): keine Rechnung -> kein Zahlbeleg möglich. Werden angezeigt und an der Kasse normal als
// Auftrag geladen (Abholung/Zahlung mit Bon = Beleg). Klicktest 2026-10-07.
$ohne = $db->prepare("
    SELECT a.id, a.auftrag_nr, a.kunden_id, a.kunden_snapshot, a.lieferart, a.bruttobetrag,
           (SELECT COALESCE(SUM(z.betrag), 0) FROM auftrag_zahlungen z WHERE z.auftrag_id = a.id) AS bezahlt
    FROM auftraege a
    WHERE a.kanal NOT IN ('kasse', 'jtl_archiv', 'haendler')
      AND a.lieferstatus NOT IN ('storniert', 'abgeschlossen', 'versendet', 'retoure_offen')
      AND a.zahlungsstatus IN ('ausstehend', 'teilbezahlt') AND a.lieferart = 'abholung'
      AND NOT EXISTS (SELECT 1 FROM rechnungen r WHERE r.auftrag_id = a.id)
      AND (a.auftrag_nr LIKE :like1 OR a.kunden_snapshot LIKE :like2)
    ORDER BY a.id DESC
    LIMIT 10
");
$ohne->execute([':like1' => $like, ':like2' => $like]);
foreach ($ohne->fetchAll(PDO::FETCH_ASSOC) as $a) {
    $k = json_decode($a['kunden_snapshot'] ?? '{}', true) ?: [];
    $treffer[] = [
        'auftrag_id'    => (int)$a['id'],
        'auftrag_nr'    => $a['auftrag_nr'],
        'kunden_id'     => $a['kunden_id'] ? (int)$a['kunden_id'] : null,
        'kunde'         => trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')) ?: ($k['firma'] ?? ($k['name'] ?? '')),
        'rechnungen'    => [],
        'offen'         => max(0, round((float)$a['bruttobetrag'] - (float)$a['bezahlt'], 2)),
        'ohne_rechnung' => true,
        'lieferart'     => $a['lieferart'],
    ];
}

echo json_encode(['erfolg' => true, 'treffer' => $treffer]);
