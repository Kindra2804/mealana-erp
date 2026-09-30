<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/modules/lager/LagerService.php';
require_once __DIR__ . '/../../../src/modules/dokumente/DokumentService.php';
require_once __DIR__ . '/../../../src/core/Mailer.php';
require_once __DIR__ . '/../../../src/core/Logger.php';
require_once __DIR__ . '/../../../src/modules/packplatz/RetourService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

$db         = Database::getInstance();
$lagerSvc   = new LagerService();
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

$auftragId  = (int)($_POST['auftrag_id']  ?? 0);
$rechnungId = (int)($_POST['rechnung_id'] ?? 0);
$lagerId    = (int)($_POST['lager_id']    ?? 0);
$ergebnis   = $_POST['ergebnis']   ?? 'nur_einbuchen';
$gsGrund    = trim($_POST['gs_grund']  ?? '');
$mailSenden = !empty($_POST['mail_senden']);
$mailNotiz  = trim($_POST['mail_notiz'] ?? '');

if (!$auftragId || !$lagerId) {
    $_SESSION['fehler'] = 'Fehlende Pflichtfelder.';
    header('Location: index.php'); exit;
}

$stmt = $db->prepare("SELECT * FROM auftraege WHERE id = :id");
$stmt->execute([':id' => $auftragId]);
$auftrag = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$auftrag) {
    $_SESSION['fehler'] = 'Auftrag nicht gefunden.';
    header('Location: index.php'); exit;
}

// ── Manager-Override: Gutschrift braucht packplatz.gutschrift oder Manager-PIN ──
// Läuft VOR dem Lager-Einbuchen weiter unten, damit bei fehlender Freigabe wirklich
// nichts gebucht wird (keine Ware im Lager ohne zugehörige Gutschrift-Entscheidung).
if ($ergebnis === 'gutschrift' && !Auth::kann('packplatz.gutschrift')) {
    $manager = Auth::pruefeManagerPin((string)($_POST['manager_pin'] ?? ''));
    if (!$manager) {
        $_SESSION['fehler'] = 'Gutschrift braucht eine Manager-Freigabe (PIN).';
        header('Location: detail.php?auftrag_id=' . $auftragId); exit;
    }
    Logger::log('manager_override', 'auftraege', $auftragId, [
        'ausgeloest_von'  => $benutzerId,
        'freigegeben_von' => $manager['id'],
        'kontext'         => 'packplatz_gutschrift',
    ]);
}

// ── Positionen filtern (nur angehakte) + gegen Doppel-Retoure/-Gutschrift prüfen ──
// Pro Position können mehrere Teile kommen (unterschiedliche Charge und/oder Zustand).
// Obergrenzen kommen aus der DB, nie aus dem Formular:
//   physisch zurück:  menge - menge_retourniert   (Kasse/Rücklagerung/frühere Retoure)
//   gutschreiben:     menge - menge_gutgeschrieben (Kasse, ERP-Gutschrift, frühere Retoure)
$erlaubteZustaende = ['neu', 'retour', 'gebraucht', 'beschaedigt', 'defekt'];
$posStmt = $db->prepare("
    SELECT ap.id, ap.artikel_id, ap.bezeichnung, ap.menge, ap.menge_retourniert, ap.menge_gutgeschrieben,
           ap.einzelpreis_netto, ap.steuer_prozent, a.charge_pflicht
    FROM auftrag_positionen ap
    LEFT JOIN artikel a ON a.id = ap.artikel_id
    WHERE ap.id = ? AND ap.auftrag_id = ?
");

$positionen = $_POST['positionen'] ?? [];
$rueckPositionen = [];
$fehlerListe = [];
foreach ($positionen as $p) {
    if (empty($p['checked'])) continue;
    $posStmt->execute([(int)($p['pos_id'] ?? 0), $auftragId]);
    $pos = $posStmt->fetch(PDO::FETCH_ASSOC);
    if (!$pos || empty($pos['artikel_id'])) continue;

    $teile = [];
    foreach (($p['teile'] ?? []) as $t) {
        $m = (int)($t['menge'] ?? 0);
        if ($m <= 0) continue;
        $zustand = in_array($t['zustand'] ?? 'neu', $erlaubteZustaende, true) ? $t['zustand'] : 'neu';
        $charge  = trim($t['charge'] ?? '') ?: null;
        if ($pos['charge_pflicht'] && !$charge && $zustand !== 'defekt') {
            $fehlerListe[] = $pos['bezeichnung'] . ': Charge ist Pflicht.';
        }
        $teile[] = ['menge' => $m, 'zustand' => $zustand, 'charge' => $charge];
    }
    $summe = array_sum(array_column($teile, 'menge'));
    if ($summe <= 0) continue;

    $offenPhysisch = (int)$pos['menge'] - (int)$pos['menge_retourniert'];
    $offenGs       = (int)$pos['menge'] - (int)$pos['menge_gutgeschrieben'];
    if ($summe > $offenPhysisch) {
        $fehlerListe[] = $pos['bezeichnung'] . ": nur noch $offenPhysisch Stück offen — der Rest ist bereits zurückgekommen (Kasse, Rücklagerung oder frühere Retoure).";
    }
    if ($ergebnis === 'gutschrift' && $summe > $offenGs) {
        $fehlerListe[] = $pos['bezeichnung'] . ": nur noch $offenGs Stück gutschreibbar — der Rest wurde bereits gutgeschrieben (Kasse oder frühere Gutschrift).";
    }

    $rueckPositionen[] = [
        'pos_id'            => (int)$pos['id'],
        'artikel_id'        => (int)$pos['artikel_id'],
        'bezeichnung'       => $pos['bezeichnung'],
        'menge'             => $summe,
        'teile'             => $teile,
        'zustand'           => implode(', ', array_unique(array_map(fn($t) => RetourService::ZUSTAND_LABELS[$t['zustand']] ?? $t['zustand'], $teile))),
        'einzelpreis_netto' => (float)$pos['einzelpreis_netto'],
        'steuer_prozent'    => (float)$pos['steuer_prozent'],
    ];
}

if (empty($rueckPositionen)) {
    $_SESSION['fehler'] = 'Bitte mindestens eine Position auswählen.';
    header('Location: detail.php?auftrag_id=' . $auftragId); exit;
}
if ($fehlerListe) {
    $_SESSION['fehler'] = implode(' ', array_unique($fehlerListe));
    header('Location: detail.php?auftrag_id=' . $auftragId); exit;
}

// ── Lager einbuchen (Zustandsregel über RetourService) ──────────────────────
$referenz = 'Retoure ' . $auftrag['auftrag_nr'];
$retourSvc = new RetourService();
$buchungsTexte = [];
$retourniertStmt = $db->prepare("UPDATE auftrag_positionen SET menge_retourniert = menge_retourniert + ? WHERE id = ?");
foreach ($rueckPositionen as $rp) {
    foreach ($rp['teile'] as $t) {
        $r = $retourSvc->einbuchen([
            'artikel_id'  => $rp['artikel_id'],
            'lager_id'    => $lagerId,
            'menge'       => $t['menge'],
            'zustand'     => $t['zustand'],
            'charge'      => $t['charge'],
            'referenz'    => $referenz,
            'notiz'       => 'Retoure',
            'benutzer_id' => $benutzerId,
        ]);
        $buchungsTexte[] = $rp['bezeichnung'] . ': ' . ($r['erfolg'] ? $r['text'] : 'FEHLER ' . ($r['fehler'] ?? ''));
    }
    $retourniertStmt->execute([$rp['menge'], $rp['pos_id']]);
}
// ── Gutschrift ───────────────────────────────────────────────────────────────
$gsPfad = null;
$gsNr   = null;
$gsBrutto = 0;

if ($ergebnis === 'gutschrift' && $rechnungId) {
    $dokumentService = new DokumentService();
    $gsPositionen    = array_map(fn($rp) => [
        'pos_id'           => $rp['pos_id'],
        'menge'            => $rp['menge'],
        'einzelpreis_netto'=> $rp['einzelpreis_netto'],
        'steuer_prozent'   => $rp['steuer_prozent'],
    ], $rueckPositionen);

    $gsResult = $dokumentService->erstelleGutschrift(
        $auftragId,
        $rechnungId,
        $benutzerId,
        'teilgutschrift',
        $gsPositionen,
        $gsGrund ?: 'Retoure',
        false // Lager wird oben separat gebucht
    );
    if ($gsResult['erfolg'] ?? false) {
        $gsNr    = $gsResult['gs_nr'] ?? null;
        $storagePfad = dirname(__DIR__, 3) . '/storage/dokumente/' . $auftragId . '/' . ($gsResult['dateiname'] ?? '');
        $gsPfad  = file_exists($storagePfad) ? $storagePfad : null;
        // Brutto aus Positionen berechnen
        foreach ($rueckPositionen as $rp) {
            $gsBrutto += Positionsrechnung::ausPosition($rp)['brutto'];
        }
    }
}

// ── Log ──────────────────────────────────────────────────────────────────────
Logger::log('retoure.verarbeitet', 'auftraege', $auftragId, [
    'ergebnis'   => $ergebnis,
    'positionen' => count($rueckPositionen),
    'gs_nr'      => $gsNr,
]);

// ── Mail ─────────────────────────────────────────────────────────────────────
if ($mailSenden) {
    $kd = json_decode($auftrag['kunden_snapshot'] ?? '{}', true) ?: [];
    $email = $kd['email'] ?? '';
    if ($email) {
        try {
            $kdName = trim(($kd['vorname'] ?? '') . ' ' . ($kd['nachname'] ?? ''));
            if (!empty($kd['firma'])) $kdName = $kd['firma'];
            if (!$kdName) $kdName = $kd['name'] ?? '';

            $mailer = new Mailer();
            $anhaenge = [];
            if ($gsPfad && file_exists($gsPfad)) {
                $anhaenge[] = ['pfad' => $gsPfad, 'name' => basename($gsPfad)];
            }

            $konfig = $db->query("SELECT schluessel, wert FROM system_einstellungen WHERE schluessel IN ('firmenname','firma_email')")->fetchAll(PDO::FETCH_KEY_PAIR);
            $mailer->sendeTemplate(
                $email,
                'Ihre Retoure zu ' . $auftrag['auftrag_nr'] . ' wurde bearbeitet',
                'mails/retoure.html.twig',
                [
                    'kunde_name'     => $kdName ?: 'Kunde',
                    'auftrag_nummer' => $auftrag['auftrag_nr'],
                    'ergebnis'       => $ergebnis,
                    'ergebnis_text'  => ucfirst($ergebnis),
                    'positionen'     => $rueckPositionen,
                    'gs_nr'          => $gsNr,
                    'gs_anhang'      => !empty($anhaenge),
                    'gs_betrag'      => $gsBrutto,
                    'notiz'          => $mailNotiz,
                    'firma_email'    => $konfig['firma_email'] ?? '',
                ],
                $anhaenge
            );
        } catch (Exception $e) {
            // Mail-Fehler nicht als fatal behandeln
        }
    }
}

$_SESSION['erfolg'] = 'Retoure verarbeitet: ' . implode(' · ', $buchungsTexte) . '.'
    . ($gsNr ? ' Gutschrift ' . $gsNr . ' erstellt.' : '')
    . ($mailSenden ? ' Mail gesendet.' : '');
header('Location: ' . BASE_PATH . '/packplatz/retoure/index.php');
exit;
