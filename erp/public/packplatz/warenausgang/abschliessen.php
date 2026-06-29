<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/core/Logger.php';
require_once __DIR__ . '/../../../src/core/Mailer.php';
require_once __DIR__ . '/../../../src/core/EasyPakExporter.php';
require_once __DIR__ . '/../../../src/modules/lager/LagerService.php';
require_once __DIR__ . '/../../../src/modules/auftraege/AuftragRepository.php';
require_once __DIR__ . '/../../../src/modules/dokumente/DokumentService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db             = Database::getInstance();
$auftragId      = (int)($_POST['auftrag_id']   ?? 0);
$picklisteId    = (int)($_POST['pickliste_id'] ?? 0) ?: null;
$tracking             = trim($_POST['tracking']              ?? '');
$versanddienstleister = trim($_POST['versanddienstleister'] ?? 'post_at');
$gewicht              = (float)($_POST['gewicht']           ?? 0);
$istTeillieferung = ($_POST['teillieferung'] ?? '0') === '1';
$positionenJson = $_POST['positionen_json'] ?? '[]';
$posGescannt    = json_decode($positionenJson, true) ?: [];

if (!$auftragId) {
    $_SESSION['fehler'] = 'Fehlende Pflichtdaten.';
    header('Location: index.php');
    exit;
}

$auftrag = $db->prepare("SELECT * FROM auftraege WHERE id = ?");
$auftrag->execute([$auftragId]);
$auftrag = $auftrag->fetch(PDO::FETCH_ASSOC);
if (!$auftrag) {
    $_SESSION['fehler'] = 'Auftrag nicht gefunden.';
    header('Location: index.php');
    exit;
}

$isAbholung = ($auftrag['lieferart'] ?? '') === 'abholung';
if (!$isAbholung && !$tracking) {
    $_SESSION['fehler'] = 'Trackingnummer fehlt.';
    header('Location: index.php');
    exit;
}

$benutzerId  = (int)($_SESSION['benutzer']['id'] ?? 0);
$lagerService = new LagerService();
$auftragRepo  = new AuftragRepository();

// ─── Auftrag-Positionen laden (GLEICHER Filter wie scan.php!) ────────────────
// scan.php filtert auf Restmenge > 0, deshalb müssen die Indizes hier identisch sein.
// Positionen die bereits vollständig geliefert wurden, werden ausgeschlossen.
$stmtPos = $db->prepare("
    SELECT id, artikel_id, menge
    FROM auftrag_positionen
    WHERE auftrag_id = ? AND artikel_id IS NOT NULL
      AND menge - COALESCE(menge_geliefert, 0) > 0
    ORDER BY sort_order, id
");
$stmtPos->execute([$auftragId]);
$posMap = array_values($stmtPos->fetchAll(PDO::FETCH_ASSOC)); // 0-indexed = JS idx

// ─── Lagerabbuchung + Status-Update in einer Transaktion ─────────────────────
$db->beginTransaction();
try {
    // Nur tatsächlich gescannte Mengen abbuchen + menge_geliefert setzen
    foreach ($posGescannt as $ps) {
        $idx      = (int)$ps['idx'];
        $gescannt = (float)($ps['gescannt'] ?? 0);
        if ($gescannt <= 0 || !isset($posMap[$idx])) continue;

        $pos = $posMap[$idx];

        $lagerService->warenausgang([
            'artikel_id'  => $pos['artikel_id'],
            'lager_id'    => 1,
            'menge'       => $gescannt,
            'referenz'    => $auftrag['auftrag_nr'],
            'notiz'       => $istTeillieferung ? 'Packplatz Teillieferung' : 'Packplatz Versand',
            'benutzer_id' => $benutzerId,
        ]);

        $db->prepare("
            UPDATE auftrag_positionen
            SET menge_geliefert = COALESCE(menge_geliefert, 0) + ?
            WHERE id = ?
        ")->execute([$gescannt, $pos['id']]);
    }

    // Lieferstatus + Tracking speichern
    $neuerStatus = $isAbholung ? 'abholbereit' : ($istTeillieferung ? 'teilgeliefert' : 'versendet');

    if ($isAbholung) {
        $db->prepare("
            UPDATE auftraege
            SET lieferstatus = ?, aktualisiert_am = NOW()
            WHERE id = ?
        ")->execute([$neuerStatus, $auftragId]);
    } else {
        $db->prepare("
            UPDATE auftraege
            SET tracking_nr = ?, versanddienstleister = ?,
                versand_tracking = ?, versand_datum = NOW(),
                lieferstatus = ?, aktualisiert_am = NOW()
            WHERE id = ?
        ")->execute([$tracking, $versanddienstleister, $tracking, $neuerStatus, $auftragId]);

        // Lieferhistory-Eintrag (nur für Versand)
        $db->prepare("
            INSERT INTO auftrag_lieferungen
                (auftrag_id, tracking_nr, versanddienstleister, ist_teillieferung, benutzer_id)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$auftragId, $tracking, $versanddienstleister ?: null, $istTeillieferung ? 1 : 0, $benutzerId]);
    }

    // Reservierungen schließen
    $auftragRepo->schliesseReservierungen($auftragId);

    // Statuslog korrekt schreiben
    $notizText = $isAbholung
        ? "Bereit zur Abholung — Abholzettel erstellt"
        : ($istTeillieferung
            ? "Teillieferung versendet — Tracking: {$tracking}"
            : "Versendet — Tracking: {$tracking}");
    $auftragRepo->logStatus($auftragId, ['lieferstatus' => [$auftrag['lieferstatus'], $neuerStatus]], $notizText, $benutzerId);

    // Pickliste abschließen (wenn alle Aufträge versendet)
    if ($picklisteId) {
        $offene = $db->prepare("
            SELECT COUNT(*) FROM pickliste_auftraege pa
            JOIN auftraege a ON a.id = pa.auftrag_id
            WHERE pa.pickliste_id = ?
              AND a.lieferstatus NOT IN ('versendet','teilgeliefert','abgeschlossen','storniert')
        ");
        $offene->execute([$picklisteId]);
        if ((int)$offene->fetchColumn() === 0) {
            $db->prepare("UPDATE picklisten SET status='abgeschlossen', abgeschlossen_am=NOW() WHERE id=?")
               ->execute([$picklisteId]);
        }
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    $_SESSION['fehler'] = 'Fehler beim Speichern: ' . $e->getMessage();
    header('Location: scan.php?modus=auftrag&auftrag_id=' . $auftragId);
    exit;
}

// ─── Logger (außerhalb Transaktion) ──────────────────────────────────────────
Logger::log('packplatz.versendet', 'auftraege', $auftragId, [
    'tracking'      => $tracking,
    'teillieferung' => $istTeillieferung,
    'gewicht'       => $gewicht,
]);

// ─── Gelieferte Positionen aufbauen (für Mail + Lieferschein) ────────────────
$geliefertePositionen = [];  // für Mail-Template (bezeichnung, artikelnummer, menge_versendet, einheit)
$gelieferteFuerPdf    = [];  // für DokumentService (menge statt menge_versendet + Preisfelder)

if (!empty($posGescannt)) {
    $stmtDet = $db->prepare("
        SELECT p.id, p.bezeichnung, p.einzelpreis_netto, p.gesamtpreis_netto,
               p.steuer_prozent, p.rabatt_prozent,
               a.artikelnummer, e.kuerzel AS einheit
        FROM auftrag_positionen p
        LEFT JOIN artikel a ON a.id = p.artikel_id
        LEFT JOIN einheiten e ON e.id = a.einheit_id
        WHERE p.auftrag_id = ? AND p.artikel_id IS NOT NULL
        ORDER BY p.sort_order, p.id
    ");
    $stmtDet->execute([$auftragId]);
    $posDetailsById = [];
    foreach ($stmtDet->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $posDetailsById[$row['id']] = $row;
    }

    foreach ($posGescannt as $ps) {
        $idx      = (int)$ps['idx'];
        $gescannt = (float)($ps['gescannt'] ?? 0);
        if ($gescannt <= 0 || !isset($posMap[$idx])) continue;

        $pos = $posMap[$idx];
        $det = $posDetailsById[$pos['id']] ?? [];

        $geliefertePositionen[] = [
            'bezeichnung'     => $det['bezeichnung'] ?? ('Pos. ' . ($idx + 1)),
            'artikelnummer'   => $det['artikelnummer'] ?? '',
            'menge_versendet' => $gescannt,
            'einheit'         => $det['einheit'] ?? 'Stk.',
        ];

        $gelieferteFuerPdf[] = [
            'bezeichnung'       => $det['bezeichnung'] ?? ('Pos. ' . ($idx + 1)),
            'artikelnummer'     => $det['artikelnummer'] ?? '',
            'menge'             => $gescannt,
            'einzelpreis_netto' => (float)($det['einzelpreis_netto'] ?? 0),
            'gesamtpreis_netto' => round((float)($det['einzelpreis_netto'] ?? 0) * $gescannt * (1 - (float)($det['rabatt_prozent'] ?? 0) / 100), 4),
            'steuer_prozent'    => (float)($det['steuer_prozent'] ?? 0),
            'rabatt_prozent'    => (float)($det['rabatt_prozent'] ?? 0),
        ];
    }
}

// ─── EasyPak XML → PLC-Ordner ────────────────────────────────────────────────
$plcOrdner = $db->query("SELECT wert FROM system_einstellungen WHERE schluessel='plc_polling_ordner'")->fetchColumn();
if ($plcOrdner && is_dir($plcOrdner) && $auftrag['lieferart'] === 'versand' && $versanddienstleister === 'post_at') {
    try {
        $exporter = new EasyPakExporter($db);
        $exporter->exportiere($auftragId, $gewicht, $plcOrdner);
    } catch (Throwable $e) {
        error_log('[EasyPak] Fehler: ' . $e->getMessage());
    }
}

// ─── Mail-Stammdaten ─────────────────────────────────────────────────────────
$kunden = json_decode($auftrag['kunden_snapshot'] ?? '{}', true);
if (!empty($auftrag['lieferadresse_snapshot'])) {
    $lieferAdr = json_decode($auftrag['lieferadresse_snapshot'], true);
} elseif (!empty($auftrag['rechnungsadresse_snapshot'])) {
    $lieferAdr = json_decode($auftrag['rechnungsadresse_snapshot'], true);
} else {
    $lieferAdr = json_decode($auftrag['kunden_snapshot'] ?? '{}', true);
}
$email    = $kunden['email']    ?? '';
$anrede   = $kunden['anrede']   ?? '';
$nachname = $kunden['nachname'] ?? '';
$name     = trim(($kunden['vorname'] ?? '') . ' ' . $nachname) ?: ($kunden['firma'] ?? '');

$mailerFirma = [];
if ($email) {
    $firma       = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);
    $mailerFirma = $firma; // alias für spätere Nutzung
}

// ─── PDF-Anhang (Rechnung oder Lieferschein) + Auto-Rechnungsmail ─────────────
$anhaenge        = [];
$autoRechnungMail = null;
$rgnRes           = null; // wird unten befüllt wenn Rechnung frisch erstellt wurde
if ($email && !$isAbholung) {
    try {
        $dokumentService = new DokumentService();
        $istBezahlt      = $auftrag['zahlungsstatus'] === 'bezahlt';

        if ($istBezahlt && !$istTeillieferung) {
            $rgnRes = $dokumentService->holeOderErstelleRechnung($auftragId, $benutzerId);
            if ($rgnRes['erfolg']) {
                $anhaenge[] = ['pfad' => $rgnRes['pfad'], 'name' => 'Rechnung_' . $auftrag['auftrag_nr'] . '.pdf'];

                // Rechnung-Mail nur wenn gerade frisch auto-erstellt (kein Doppelversand)
                if ($rgnRes['neu_erstellt']) {
                    $rStmt = $db->prepare("SELECT rechnung_nr, bruttobetrag FROM rechnungen WHERE auftrag_id = ? AND storniert = 0 ORDER BY erstellt_am DESC LIMIT 1");
                    $rStmt->execute([$auftragId]);
                    $autoRechnungMail = $rStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            }
        } else {
            $lsResult = $dokumentService->erstelleLieferscheinFuerLieferung($auftragId, $benutzerId, $gelieferteFuerPdf);
            if ($lsResult['erfolg']) {
                $anhaenge[] = [
                    'pfad' => $dokumentService->getDateipfad($auftragId, $lsResult['dateiname']),
                    'name' => 'Lieferschein_' . $auftrag['auftrag_nr'] . '.pdf',
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('[VersandPDF] Fehler: ' . $e->getMessage());
    }
}

// ─── Versandmail ──────────────────────────────────────────────────────────────
if ($email && !$isAbholung) {
    $trackingUrlBase = [
        'post_at' => 'https://www.post.at/sv/sendungsdetails?snr=',
        'dhl'     => 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode=',
        'dpd'     => 'https://tracking.dpd.de/status/de_DE/parcel/',
        'gls'     => 'https://gls-group.eu/track/',
        'manuell' => '',
    ];
    $dlLabels = [
        'post_at' => 'Österreichische Post',
        'dhl'     => 'DHL',
        'dpd'     => 'DPD',
        'gls'     => 'GLS',
        'manuell' => 'Paketdienst',
    ];
    $baseUrl     = $trackingUrlBase[$versanddienstleister] ?? '';
    $trackingUrl = $baseUrl ? $baseUrl . urlencode($tracking) : '';
    $betreff     = $istTeillieferung
        ? 'Teillieferung zu Auftrag ' . $auftrag['auftrag_nr'] . ' versendet'
        : 'Ihr Auftrag ' . $auftrag['auftrag_nr'] . ' wurde versendet';
    try {
        $mailer = new Mailer();
        $mailer->sendeTemplate(
            empfaenger:   $email,
            betreff:      $betreff,
            templatePfad: 'mails/versandbestaetigung.html.twig',
            variablen: [
                'logo_base64'               => $mailer->ladeShopLogo((int)($auftrag['shop_id'] ?? 1)),
                'anrede'                    => $anrede,
                'nachname'                  => $nachname,
                'kunde_name'                => $name,
                'auftrag_nummer'            => $auftrag['auftrag_nr'],
                'tracking'                  => $tracking,
                'tracking_url'              => $trackingUrl,
                'versanddienstleister_label'=> $dlLabels[$versanddienstleister] ?? $versanddienstleister,
                'ist_teillieferung'         => $istTeillieferung,
                'positionen'                => $geliefertePositionen,
                'lieferadresse'             => $lieferAdr,
                'firma_email'               => $firma['mail_from_address'] ?? '',
            ],
            anhaenge: $anhaenge,
        );
    } catch (Throwable $e) {
        error_log('[Versandmail] Fehler: ' . $e->getMessage());
    }

    // Auto-Rechnungsmail: nur wenn Rechnung gerade frisch auto-erstellt wurde
    if ($autoRechnungMail && $email) {
        try {
            $mailerR = new Mailer();
            $mailerR->sendeTemplate(
                empfaenger:   $email,
                betreff:      'Ihre Rechnung ' . $autoRechnungMail['rechnung_nr'],
                templatePfad: 'mails/rechnung_mail.html.twig',
                variablen: [
                    'logo_base64'    => $mailerR->ladeShopLogo((int)($auftrag['shop_id'] ?? 1)),
                    'anrede'         => $anrede,
                    'nachname'       => $nachname,
                    'kunde_name'     => $name,
                    'auftrag_nummer' => $auftrag['auftrag_nr'],
                    'rechnung_nr'    => $autoRechnungMail['rechnung_nr'],
                    'brutto_gesamt'  => (float)$autoRechnungMail['bruttobetrag'],
                    'faellig_datum'  => date('d.m.Y', strtotime('+14 days')),
                    'firma_email'    => $firma['mail_from_address'] ?? '',
                ],
                anhaenge: [['pfad' => $rgnRes['pfad'], 'name' => $autoRechnungMail['rechnung_nr'] . '.pdf']],
            );
        } catch (Throwable $e) {
            error_log('[AutoRechnungMail] Fehler: ' . $e->getMessage());
        }
    }
}

// ─── Abholzettel + Abholmail ─────────────────────────────────────────────────
if ($isAbholung) {
    $abholAnhaenge = [];
    try {
        $dokumentService = new DokumentService();
        $azRes = $dokumentService->erstelleAbholzettel($auftragId, $benutzerId);
        if ($azRes['erfolg']) {
            $abholAnhaenge[] = [
                'pfad' => $dokumentService->getDateipfad($auftragId, $azRes['dateiname']),
                'name' => 'Abholzettel_' . $auftrag['auftrag_nr'] . '.pdf',
            ];
        }
    } catch (Throwable $e) {
        error_log('[Abholzettel] Fehler: ' . $e->getMessage());
    }

    if ($email) {
        if (!isset($firma)) {
            $firma = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        try {
            $mailerAb = new Mailer();
            $mailerAb->sendeTemplate(
                empfaenger:   $email,
                betreff:      'Ihre Bestellung ' . $auftrag['auftrag_nr'] . ' liegt zur Abholung bereit',
                templatePfad: 'mails/fertig_zur_abholung.html.twig',
                variablen: [
                    'logo_base64'    => $mailerAb->ladeShopLogo((int)($auftrag['shop_id'] ?? 1)),
                    'anrede'         => $anrede,
                    'nachname'       => $nachname,
                    'kunde_name'     => $name,
                    'auftrag_nummer' => $auftrag['auftrag_nr'],
                    'positionen'     => $geliefertePositionen,
                    'firma_adresse'  => trim(($firma['strasse'] ?? '') . ' ' . ($firma['hausnummer'] ?? '')),
                    'firma_plz_ort'  => trim(($firma['plz'] ?? '') . ' ' . ($firma['ort'] ?? '')),
                    'firma_email'    => $firma['mail_from_address'] ?? '',
                    'firma_tel'      => $firma['telefon'] ?? '',
                ],
                anhaenge: $abholAnhaenge,
            );
        } catch (Throwable $e) {
            error_log('[Abholmail] Fehler: ' . $e->getMessage());
        }
    }
}

header('Location: index.php');
exit;
