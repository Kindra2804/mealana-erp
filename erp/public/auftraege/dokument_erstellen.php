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

        $anrede   = $kunde['anrede']   ?? '';
        $nachname = $kunde['nachname'] ?? '';
        $kundeName = trim(($kunde['vorname'] ?? '') . ' ' . $nachname);
        if (!$kundeName) $kundeName = $kunde['firma'] ?? $email;

        $firmaEmail = $db->query("SELECT wert FROM system_einstellungen WHERE schluessel='mail_from_address'")
                        ->fetchColumn() ?: '';

        $logoBase64  = $mailer->ladeShopLogo((int)($auftrag['shop_id'] ?? 1));
        $storagePfad = __DIR__ . '/../../storage/dokumente/' . $auftragId . '/' . $ergebnis['dateiname'];

        if ($typ === 'auftragsbestaetigung') {

            // Positionen mit Artikelnummer + Lagerbestand für ÜV-Check
            $pStmt = $db->prepare("
                SELECT p.id, p.bezeichnung, p.menge,
                       p.einzelpreis_netto, p.gesamtpreis_netto, p.steuer_prozent,
                       a.artikelnummer,
                       COALESCE(SUM(lb.bestand), 0) AS verfuegbar_bestand
                FROM auftrag_positionen p
                LEFT JOIN artikel a ON a.id = p.artikel_id
                LEFT JOIN lagerbestand lb ON lb.artikel_id = p.artikel_id
                WHERE p.auftrag_id = :id
                GROUP BY p.id
                ORDER BY p.sort_order, p.id
            ");
            $pStmt->execute([':id' => $auftragId]);
            $rohdaten = $pStmt->fetchAll(PDO::FETCH_ASSOC);

            $positionen   = [];
            $positionenUv = [];
            $nettoGesamt  = 0.0;
            $bruttoGesamt = 0.0;

            foreach ($rohdaten as $pos) {
                $einzelBrutto = round($pos['einzelpreis_netto'] * (1 + $pos['steuer_prozent'] / 100), 2);
                $gesamtBrutto = round($pos['gesamtpreis_netto'] * (1 + $pos['steuer_prozent'] / 100), 2);
                $bruttoGesamt += $gesamtBrutto;
                $nettoGesamt  += (float)$pos['gesamtpreis_netto'];

                $istUv = (float)$pos['verfuegbar_bestand'] < (float)$pos['menge'];

                $positionen[] = [
                    'bezeichnung'       => $pos['bezeichnung'],
                    'menge'             => $pos['menge'],
                    'artikelnummer'     => $pos['artikelnummer'] ?? '',
                    'einzelpreis_brutto'=> $einzelBrutto,
                    'gesamt_brutto'     => $gesamtBrutto,
                    'lieferzeit_text'   => $istUv ? 'bestellbar / Lieferverzögerung möglich' : '',
                ];

                if ($istUv) {
                    $positionenUv[] = [
                        'bezeichnung' => $pos['bezeichnung'],
                        'menge'       => $pos['menge'],
                        'bestand'     => (int)$pos['verfuegbar_bestand'],
                    ];
                }
            }

            $allesLagernd   = empty($positionenUv);
            $mwstGesamt     = round($bruttoGesamt - $nettoGesamt, 2);
            $zahlungsstatus = $auftrag['zahlungsstatus'] ?? '';
            $zahlungsart    = $auftrag['zahlungsart']    ?? '';

            // Adressen
            $raSnapshot  = json_decode($auftrag['rechnungsadresse_snapshot'] ?? '{}', true) ?: $kunde;
            $laSnapshot  = json_decode($auftrag['lieferadresse_snapshot']    ?? '{}', true) ?: [];
            // Lieferadresse nur anzeigen wenn sie von der Rechnungsadresse abweicht
            $laNachname  = trim(($laSnapshot['nachname'] ?? '') . ($laSnapshot['strasse'] ?? ''));
            $raNachname  = trim(($raSnapshot['nachname'] ?? '') . ($raSnapshot['strasse'] ?? ''));
            $lieferadresse = (!empty($laSnapshot) && $laNachname !== $raNachname) ? $laSnapshot : [];

            $mailer->sendeTemplate(
                $email,
                'Auftragsbestätigung ' . $auftrag['auftrag_nr'],
                'mails/auftragsbestaetigung.html.twig',
                [
                    'logo_base64'    => $logoBase64,
                    'anrede'         => $anrede,
                    'nachname'       => $nachname,
                    'kunde_name'     => $kundeName,
                    'auftrag_nummer' => $auftrag['auftrag_nr'],
                    'positionen'     => $positionen,
                    'positionen_uv'  => $positionenUv,
                    'alles_lagernd'  => $allesLagernd,
                    'brutto_gesamt'  => $bruttoGesamt,
                    'mwst_gesamt'    => $mwstGesamt,
                    'zahlungsart'    => $zahlungsart,
                    'zahlungsstatus' => $zahlungsstatus,
                    'rechnungsadresse' => $raSnapshot,
                    'lieferadresse'  => $lieferadresse,
                    'datum_heute'    => date('d.m.Y'),
                    'firma_email'    => $firmaEmail,
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
                    'logo_base64'    => $logoBase64,
                    'anrede'         => $anrede,
                    'nachname'       => $nachname,
                    'kunde_name'     => $kundeName,
                    'auftrag_nummer' => $auftrag['auftrag_nr'],
                    'rechnung_nr'    => $rechnung['rechnung_nr'],
                    'brutto_gesamt'  => (float)$rechnung['bruttobetrag'],
                    'faellig_datum'  => date('d.m.Y', strtotime('+14 days')),
                    'firma_email'    => $firmaEmail,
                ],
                [['pfad' => $storagePfad, 'name' => $rechnung['rechnung_nr'] . '.pdf']]
            );
        }

    } catch (Throwable $e) {
        error_log('[Dokument Mail] ' . $e->getMessage());
    }
}
