<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/dokumente/DokumentService.php';
require_once __DIR__ . '/../../src/core/Mailer.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

$auftragId = (int)($_POST['auftrag_id'] ?? 0);
$typ       = trim($_POST['typ'] ?? '');

if (!$auftragId || !$typ) {
    $_SESSION['fehler'] = ['Ungültige Anfrage.'];
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

$service    = new DokumentService();
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

$ergebnis = match($typ) {
    'rechnung'             => $service->erstelleRechnung($auftragId, $benutzerId),
    'auftragsbestaetigung' => $service->erstelleAuftragsbestaetigung($auftragId, $benutzerId),
    'lieferschein'         => $service->erstelleLieferschein($auftragId, $benutzerId),
    'abholzettel'          => $service->erstelleAbholzettel($auftragId, $benutzerId),
    default                => ['erfolg' => false, 'fehler' => 'Unbekannter Dokumenttyp.'],
};

if ($ergebnis['erfolg']) {
    // Mail versenden für AB und Rechnung
    if (in_array($typ, ['auftragsbestaetigung', 'rechnung'])) {
        versendeDokumentMail($auftragId, $typ, $ergebnis);
    }

    $_SESSION['erfolg'] = 'Dokument wurde erstellt.';
    header('Location: /mealana/auftraege/dokument_download.php?auftrag_id=' . $auftragId
        . '&datei=' . urlencode($ergebnis['dateiname']));
} else {
    $_SESSION['fehler'] = [$ergebnis['fehler'] ?? 'Fehler beim Erstellen des Dokuments.'];
    header('Location: /mealana/auftraege/detail.php?id=' . $auftragId);
}
exit;

// ── Mail-Versand ────────────────────────────────────────────────────────────

function versendeDokumentMail(int $auftragId, string $typ, array $ergebnis): void
{
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
            WHERE schluessel IN ('firmenname','mail_from_address','firma_iban')
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        $storagePfad = __DIR__ . '/../../storage/dokumente/' . $auftragId . '/' . $ergebnis['dateiname'];

        if ($typ === 'auftragsbestaetigung') {
            $pStmt = $db->prepare("
                SELECT bezeichnung, menge,
                       einzelpreis_netto, gesamtpreis_netto, steuer_prozent
                FROM auftrag_positionen
                WHERE auftrag_id = :id
                ORDER BY sort_order, id
            ");
            $pStmt->execute([':id' => $auftragId]);
            $positionen    = $pStmt->fetchAll(PDO::FETCH_ASSOC);
            $bruttoGesamt  = 0.0;
            foreach ($positionen as &$pos) {
                $pos['einzelpreis_brutto'] = round($pos['einzelpreis_netto'] * (1 + $pos['steuer_prozent'] / 100), 2);
                $pos['gesamt_brutto']      = round($pos['gesamtpreis_netto'] * (1 + $pos['steuer_prozent'] / 100), 2);
                $bruttoGesamt += $pos['gesamt_brutto'];
            }
            unset($pos);

            $mailer->sendeTemplate(
                $email,
                'Auftragsbestätigung ' . $auftrag['auftrag_nr'],
                'mails/auftragsbestaetigung.html.twig',
                [
                    'kunde_name'     => $kundeName,
                    'auftrag_nummer' => $auftrag['auftrag_nr'],
                    'positionen'     => $positionen,
                    'brutto_gesamt'  => $bruttoGesamt,
                    'zahlungsart'    => $auftrag['zahlungsart'] ?? '',
                    'datum_heute'    => date('d.m.Y'),
                    'firma_email'    => $einst['mail_from_address'] ?? '',
                    'firma_iban'     => $einst['firma_iban'] ?? '',
                ]
            );

        } elseif ($typ === 'rechnung') {
            $rStmt = $db->prepare("
                SELECT rechnung_nr, bruttobetrag FROM rechnungen
                WHERE auftrag_id = :id AND storniert = 0
                ORDER BY erstellt_am DESC LIMIT 1
            ");
            $rStmt->execute([':id' => $auftragId]);
            $rechnung = $rStmt->fetch(PDO::FETCH_ASSOC);
            if (!$rechnung) return;

            $mailer->sendeTemplate(
                $email,
                'Ihre Rechnung ' . $rechnung['rechnung_nr'],
                'mails/rechnung_mail.html.twig',
                [
                    'kunde_name'     => $kundeName,
                    'auftrag_nummer' => $auftrag['auftrag_nr'],
                    'rechnung_nr'    => $rechnung['rechnung_nr'],
                    'brutto_gesamt'  => (float)$rechnung['bruttobetrag'],
                    'faellig_datum'  => date('d.m.Y', strtotime('+14 days')),
                    'firma_email'    => $einst['mail_from_address'] ?? '',
                    'firma_iban'     => $einst['firma_iban'] ?? '',
                ],
                [['pfad' => $storagePfad, 'name' => $rechnung['rechnung_nr'] . '.pdf']]
            );
        }

    } catch (Throwable $e) {
        error_log('[Dokument Mail] ' . $e->getMessage());
    }
}
