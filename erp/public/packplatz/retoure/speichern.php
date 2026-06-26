<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/modules/lager/LagerService.php';
require_once __DIR__ . '/../../../src/modules/dokumente/DokumentService.php';
require_once __DIR__ . '/../../../src/core/Mailer.php';
require_once __DIR__ . '/../../../src/core/Logger.php';

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

// ── Positionen filtern (nur angehakte) ─────────────────────────────────────
$positionen = $_POST['positionen'] ?? [];
$rueckPositionen = [];
foreach ($positionen as $p) {
    if (empty($p['checked'])) continue;
    $menge = max(1, (int)($p['menge'] ?? 1));
    $rueckPositionen[] = [
        'pos_id'           => (int)$p['pos_id'],
        'artikel_id'       => (int)$p['artikel_id'],
        'bezeichnung'      => $p['bezeichnung'] ?? '',
        'menge'            => $menge,
        'zustand'          => $p['zustand'] ?? 'neu',
        'einzelpreis_netto'=> (float)($p['einzelpreis_netto'] ?? 0),
        'steuer_prozent'   => (float)($p['steuer_prozent'] ?? 0),
    ];
}

if (empty($rueckPositionen)) {
    $_SESSION['fehler'] = 'Bitte mindestens eine Position auswählen.';
    header('Location: detail.php?auftrag_id=' . $auftragId); exit;
}

// ── Lager einbuchen ─────────────────────────────────────────────────────────
$referenz = 'Retoure ' . $auftrag['auftrag_nr'];
foreach ($rueckPositionen as $rp) {
    $lagerSvc->wareneingang([
        'artikel_id'  => $rp['artikel_id'],
        'lager_id'    => $lagerId,
        'menge'       => $rp['menge'],
        'charge'      => null,
        'referenz'    => $referenz,
        'notiz'       => 'Retoure — Zustand: ' . $rp['zustand'],
        'benutzer_id' => $benutzerId,
    ]);
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
            $gsBrutto += round($rp['einzelpreis_netto'] * $rp['menge'] * (1 + $rp['steuer_prozent'] / 100), 2);
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

$_SESSION['erfolg'] = 'Retoure verarbeitet: ' . count($rueckPositionen) . ' Position(en) eingebucht.'
    . ($gsNr ? ' Gutschrift ' . $gsNr . ' erstellt.' : '')
    . ($mailSenden ? ' Mail gesendet.' : '');
header('Location: /mealana/packplatz/retoure/index.php');
exit;
