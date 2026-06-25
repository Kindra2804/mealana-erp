<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/dokumente/DokumentService.php';
require_once __DIR__ . '/../../src/core/Mailer.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

$auftragId  = (int)($_POST['auftrag_id'] ?? 0);
$rechnungId = (int)($_POST['rechnung_id'] ?? 0);
$gsArt      = trim($_POST['gs_art'] ?? 'teilgutschrift');
$grund      = trim($_POST['grund'] ?? '');
$lagerRueck = !empty($_POST['lager_rueckbuchen']);
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

if (!$auftragId || !$rechnungId || !$benutzerId) {
    $_SESSION['fehler'] = ['Ungültige Anfrage.'];
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

// Positionen aus POST aufbereiten (bei Teilgutschrift)
$positionen = [];
if ($gsArt === 'teilgutschrift' && !empty($_POST['positionen'])) {
    foreach ($_POST['positionen'] as $item) {
        if (empty($item['aktiv'])) continue;
        $menge = max(1, (int)($item['menge'] ?? 1));
        $positionen[] = [
            'pos_id'           => (int)($item['pos_id'] ?? 0),
            'menge'            => $menge,
            'steuer_prozent'   => (float)($item['steuer_prozent'] ?? 20),
            'einzelpreis_netto'=> (float)($item['einzelpreis_netto'] ?? 0),
            'artikel_id'       => (int)($item['artikel_id'] ?? 0),
        ];
    }
    if (empty($positionen)) {
        $_SESSION['fehler']   = ['Bitte mindestens eine Position auswählen.'];
        $_SESSION['formdata'] = $_POST;
        header('Location: /mealana/auftraege/gutschrift_erstellen.php?auftrag_id=' . $auftragId);
        exit;
    }
}

$service  = new DokumentService();
$ergebnis = $service->erstelleGutschrift(
    $auftragId, $rechnungId, $benutzerId,
    $gsArt, $positionen, $grund, $lagerRueck
);

if ($ergebnis['erfolg']) {
    // Gutschrift-Mail mit PDF-Anhang versenden
    versendeGutschriftMail($auftragId, $rechnungId, $gsArt, $grund, $ergebnis);

    $_SESSION['erfolg'] = 'Gutschrift ' . $ergebnis['gs_nr'] . ' wurde erstellt.';
    header('Location: /mealana/auftraege/dokument_download.php?auftrag_id=' . $auftragId
        . '&datei=' . urlencode($ergebnis['dateiname']));
} else {
    $_SESSION['fehler']   = [$ergebnis['fehler'] ?? 'Fehler beim Erstellen der Gutschrift.'];
    $_SESSION['formdata'] = $_POST;
    header('Location: /mealana/auftraege/gutschrift_erstellen.php?auftrag_id=' . $auftragId);
}
exit;

// ── Mail-Versand ────────────────────────────────────────────────────────────

function versendeGutschriftMail(
    int    $auftragId,
    int    $rechnungId,
    string $gsArt,
    string $grund,
    array  $ergebnis
): void {
    try {
        $db     = Database::getInstance();
        $mailer = new Mailer();

        $aStmt = $db->prepare("SELECT * FROM auftraege WHERE id = :id");
        $aStmt->execute([':id' => $auftragId]);
        $auftrag = $aStmt->fetch(PDO::FETCH_ASSOC);
        if (!$auftrag) return;

        $kunde = json_decode($auftrag['kunden_snapshot'] ?? '{}', true) ?: [];
        $email = trim($kunde['email'] ?? '');
        if (!$email) return;

        $kundeName = trim(($kunde['vorname'] ?? '') . ' ' . ($kunde['nachname'] ?? ''));
        if (!$kundeName) $kundeName = $kunde['firma'] ?? $email;

        $einst = $db->query("
            SELECT schluessel, wert FROM system_einstellungen
            WHERE schluessel IN ('firmenname','mail_from_address')
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        // Rechnungsnummer für Referenz
        $rStmt = $db->prepare("SELECT rechnung_nr FROM rechnungen WHERE id = :id");
        $rStmt->execute([':id' => $rechnungId]);
        $rechnung = $rStmt->fetch(PDO::FETCH_ASSOC);

        // Gutschriftsbetrag aus der Gutschrift-Tabelle
        $gsStmt = $db->prepare("
            SELECT bruttobetrag FROM gutschriften WHERE gs_nr = :nr LIMIT 1
        ");
        $gsStmt->execute([':nr' => $ergebnis['gs_nr']]);
        $gsRow = $gsStmt->fetch(PDO::FETCH_ASSOC);
        // Falls gutschriften-Tabelle nicht existiert: Betrag aus rechnungen ableiten
        $bruttoGesamt = $gsRow ? (float)$gsRow['bruttobetrag'] : 0.0;

        $storagePfad = __DIR__ . '/../../storage/dokumente/' . $auftragId . '/' . $ergebnis['dateiname'];

        $mailer->sendeTemplate(
            $email,
            'Ihre Gutschrift ' . $ergebnis['gs_nr'],
            'mails/gutschrift_mail.html.twig',
            [
                'kunde_name'     => $kundeName,
                'auftrag_nummer' => $auftrag['auftrag_nr'],
                'gs_nr'          => $ergebnis['gs_nr'],
                'rechnung_nr'    => $rechnung['rechnung_nr'] ?? '',
                'gs_art'         => $gsArt,
                'brutto_gesamt'  => $bruttoGesamt,
                'grund'          => $grund,
                'firma_email'    => $einst['mail_from_address'] ?? '',
            ],
            [['pfad' => $storagePfad, 'name' => $ergebnis['gs_nr'] . '.pdf']]
        );

    } catch (Throwable $e) {
        error_log('[Gutschrift Mail] ' . $e->getMessage());
    }
}
