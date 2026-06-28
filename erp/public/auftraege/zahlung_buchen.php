<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';
require_once __DIR__ . '/../../src/core/Mailer.php';
require_once __DIR__ . '/../../src/core/Database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$auftragId    = (int)($_POST['auftrag_id'] ?? 0);
$betrag       = (float)str_replace(',', '.', $_POST['betrag'] ?? '0');
$buchungsdatum = trim($_POST['buchungsdatum'] ?? '');
$notiz        = trim($_POST['notiz'] ?? '') ?: null;

if (!$auftragId) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Auftrag-ID fehlt']);
    exit;
}
if (!$buchungsdatum || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $buchungsdatum)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültiges Buchungsdatum']);
    exit;
}

$service  = new AuftragService();
$ergebnis = $service->bucheZahlung($auftragId, $betrag, $buchungsdatum, $notiz);

// JSON zuerst ausgeben — Mail-Fehler dürfen die Antwort nicht blockieren
echo json_encode($ergebnis);

if (!$ergebnis['erfolg']) exit;

// ─── Zahlungseingangs-Mail ────────────────────────────────────────────────────
// Nicht senden für Bar/Karte (Sofortzahlung an der Kasse) und Nachnahme
try {
    $db      = Database::getInstance();
    $aStmt   = $db->prepare("SELECT * FROM auftraege WHERE id = ?");
    $aStmt->execute([$auftragId]);
    $auftrag = $aStmt->fetch(PDO::FETCH_ASSOC);

    if (!$auftrag) exit;

    $zahlungsart = $auftrag['zahlungsart'] ?? '';
    if (in_array($zahlungsart, ['bar', 'karte', 'nachnahme'])) exit;

    $kunde    = json_decode($auftrag['kunden_snapshot'] ?? '{}', true) ?: [];
    $email    = trim($kunde['email'] ?? '');
    if (!$email) exit;

    $anrede   = $kunde['anrede']   ?? '';
    $nachname = $kunde['nachname'] ?? '';
    $kundeName = trim(($kunde['vorname'] ?? '') . ' ' . $nachname) ?: ($kunde['firma'] ?? '');

    // Bestellpositionen für Übersicht
    $pStmt = $db->prepare("
        SELECT p.bezeichnung, p.menge, p.gesamtpreis_netto, p.steuer_prozent
        FROM auftrag_positionen p
        WHERE p.auftrag_id = ?
        ORDER BY p.sort_order, p.id
    ");
    $pStmt->execute([$auftragId]);
    $rohdaten = $pStmt->fetchAll(PDO::FETCH_ASSOC);

    $positionen   = [];
    $nettoGesamt  = 0.0;
    $bruttoGesamt = (float)($auftrag['bruttobetrag'] ?? 0);

    foreach ($rohdaten as $pos) {
        $gesamtBrutto = round($pos['gesamtpreis_netto'] * (1 + $pos['steuer_prozent'] / 100), 2);
        $nettoGesamt += (float)$pos['gesamtpreis_netto'];
        $positionen[] = [
            'bezeichnung'  => $pos['bezeichnung'],
            'menge'        => $pos['menge'],
            'gesamt_brutto'=> $gesamtBrutto,
        ];
    }

    $mwstGesamt   = round($bruttoGesamt - $nettoGesamt, 2);
    $summeBezahlt = (float)$ergebnis['summe'];
    $offenBetrag  = max(0, round($bruttoGesamt - $summeBezahlt, 2));
    $neuerStatus  = $ergebnis['neuer_status'];

    $zahlungsartLabels = [
        'vorkasse' => 'Überweisung',
        'paypal'   => 'PayPal',
        'rechnung' => 'Kauf auf Rechnung',
    ];
    $zahlungsartLabel = $zahlungsartLabels[$zahlungsart] ?? ucfirst($zahlungsart);

    $buchungsDatumFormatiert = date('d.m.Y', strtotime($buchungsdatum));

    $mailer = new Mailer();
    $mailer->sendeTemplate(
        empfaenger:   $email,
        betreff:      'Ihre Zahlung für Bestellung ' . $auftrag['auftrag_nr'] . ' vom ' . $buchungsDatumFormatiert . ' ist eingegangen',
        templatePfad: 'mails/zahlungseingang.html.twig',
        variablen: [
            'logo_base64'      => $mailer->ladeShopLogo((int)($auftrag['shop_id'] ?? 1)),
            'anrede'           => $anrede,
            'nachname'         => $nachname,
            'kunde_name'       => $kundeName,
            'auftrag_nummer'   => $auftrag['auftrag_nr'],
            'buchungsdatum'    => $buchungsDatumFormatiert,
            'betrag_eingegangen' => $betrag,
            'zahlungsart_label'=> $zahlungsartLabel,
            'neuer_status'     => $neuerStatus,
            'positionen'       => $positionen,
            'brutto_gesamt'    => $bruttoGesamt,
            'mwst_gesamt'      => $mwstGesamt,
            'summe_bezahlt'    => $summeBezahlt,
            'offen_betrag'     => $offenBetrag,
            'firma_email'      => $db->query("SELECT wert FROM system_einstellungen WHERE schluessel='mail_from_address'")->fetchColumn() ?: '',
        ],
    );
} catch (Throwable $e) {
    error_log('[ZahlungsMail] ' . $e->getMessage());
}
