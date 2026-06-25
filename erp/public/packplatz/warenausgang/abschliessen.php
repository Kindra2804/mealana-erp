<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/core/Logger.php';
require_once __DIR__ . '/../../../src/core/Mailer.php';
require_once __DIR__ . '/../../../src/core/EasyPakExporter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db             = Database::getInstance();
$auftragId      = (int)($_POST['auftrag_id']   ?? 0);
$picklisteId    = (int)($_POST['pickliste_id'] ?? 0) ?: null;
$tracking       = trim($_POST['tracking']      ?? '');
$gewicht        = (float)($_POST['gewicht']    ?? 0);
$istTeillieferung = ($_POST['teillieferung'] ?? '0') === '1';
$positionenJson = $_POST['positionen_json'] ?? '[]';
$posGescannt    = json_decode($positionenJson, true) ?: [];

if (!$auftragId || !$tracking) {
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

$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

$db->beginTransaction();
try {
    // ─── Versandtracking + Datum speichern ───────────────────────────────────
    $db->prepare("
        UPDATE auftraege
        SET versand_tracking  = ?,
            versand_datum     = NOW(),
            lieferstatus      = ?,
            aktualisiert_am   = NOW()
        WHERE id = ?
    ")->execute([$tracking, $istTeillieferung ? 'teilgeliefert' : 'versendet', $auftragId]);

    // ─── Teillieferung: übrige Positionen aktualisieren ──────────────────────
    if ($istTeillieferung) {
        foreach ($posGescannt as $pos) {
            $gescannt = (int)$pos['gescannt'];
            $rest     = (int)$pos['gesamt'] - $gescannt;
            // Gescannte Menge = diese Lieferung → Rest bleibt im Auftrag
            // Wir buchen die gesendete Menge als eigene Position (vereinfacht:
            // wir aktualisieren die Menge auf den Restwert)
            if ($rest > 0) {
                // Auftrag bleibt offen mit Restmenge — kein Update nötig
                // (vollständige Teillieferungs-Split-Logik kommt in Phase 2)
            }
        }
    }

    // ─── Statuslog ───────────────────────────────────────────────────────────
    $aktion = $istTeillieferung
        ? "Teillieferung versendet — Tracking: {$tracking}"
        : "Versendet — Tracking: {$tracking}";
    $db->prepare("
        INSERT INTO auftrag_status_log (auftrag_id, aktion, erstellt_von, erstellt_am)
        VALUES (?, ?, ?, NOW())
    ")->execute([$auftragId, $aktion, $benutzerId]);

    // ─── Pickliste abschließen (wenn alle Aufträge dieser Liste versendet) ──
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

// ─── EasyPak XML → PLC-Ordner ────────────────────────────────────────────────
$plcOrdner = $db->query("SELECT wert FROM system_einstellungen WHERE schluessel='plc_polling_ordner'")->fetchColumn();
if ($plcOrdner && is_dir($plcOrdner) && $auftrag['lieferart'] === 'versand') {
    try {
        $exporter = new EasyPakExporter($db);
        $exporter->exportiere($auftragId, $gewicht, $plcOrdner);
    } catch (Throwable $e) {
        error_log('[EasyPak] Fehler: ' . $e->getMessage());
    }
}

// ─── Versandmail senden ───────────────────────────────────────────────────────
$kunden = json_decode($auftrag['kunden_snapshot'] ?? '{}', true);
if (!empty($auftrag['lieferadresse_snapshot'])) {
    $lieferAdr = json_decode($auftrag['lieferadresse_snapshot'], true);
} elseif (!empty($auftrag['rechnungsadresse_snapshot'])) {
    $lieferAdr = json_decode($auftrag['rechnungsadresse_snapshot'], true);
} else {
    $lieferAdr = json_decode($auftrag['kunden_snapshot'] ?? '{}', true);
}
$email  = $kunden['email'] ?? '';
$name   = trim(($kunden['vorname'] ?? '') . ' ' . ($kunden['nachname'] ?? '')) ?: ($kunden['firma'] ?? '');

if ($email && !$istTeillieferung) {
    try {
        $firma = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);
        $mailer = new Mailer();
        $mailer->sendeTemplate(
            empfaenger:  $email,
            betreff:     'Ihr Auftrag ' . $auftrag['auftragsnummer'] . ' wurde versendet',
            templatePfad: 'mails/versandbestaetigung.html.twig',
            variablen: [
                'kunde_name'     => $name,
                'auftrag_nummer' => $auftrag['auftragsnummer'],
                'tracking'       => $tracking,
                'lieferadresse'  => $lieferAdr,
                'firma_email'    => $firma['email'] ?? '',
                'firmenname'     => $firma['firmenname'] ?? 'MEALANA KG',
            ]
        );
    } catch (Throwable $e) {
        error_log('[Versandmail] Fehler: ' . $e->getMessage());
    }
}

// ─── Abholer-Info (kein Tracking, nur Benachrichtigung) ──────────────────────
if ($email && $auftrag['lieferart'] === 'abholung') {
    try {
        $firma  = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);
        $mailer = new Mailer();
        $mailer->sende(
            empfaenger: $email,
            betreff:    'Ihre Bestellung ' . $auftrag['auftragsnummer'] . ' liegt zur Abholung bereit',
            htmlBody:   '<p>Sehr geehrte/r ' . htmlspecialchars($name) . ',</p>'
                . '<p>Ihre Bestellung <strong>' . htmlspecialchars($auftrag['auftragsnummer']) . '</strong> '
                . 'liegt ab sofort zur Abholung bei uns bereit.</p>'
                . '<p>Mit freundlichen Grüßen,<br>' . htmlspecialchars($firma['firmenname'] ?? 'MEALANA KG') . '</p>'
        );
    } catch (Throwable $e) {
        error_log('[Abholmail] Fehler: ' . $e->getMessage());
    }
}

Logger::log('packplatz.versendet', 'auftraege', $auftragId, [
    'tracking'       => $tracking,
    'teillieferung'  => $istTeillieferung,
    'gewicht'        => $gewicht,
]);

// Zurück zur Übersicht (nächster Auftrag der Pickliste oder Hauptübersicht)
if ($picklisteId) {
    // Nächsten offenen Auftrag dieser Pickliste suchen
    $naechster = $db->prepare("
        SELECT pa.auftrag_id FROM pickliste_auftraege pa
        JOIN auftraege a ON a.id = pa.auftrag_id
        WHERE pa.pickliste_id = ?
          AND a.lieferstatus NOT IN ('versendet','teilgeliefert','abgeschlossen','storniert')
        LIMIT 1
    ");
    $naechster->execute([$picklisteId]);
    $naechsterId = $naechster->fetchColumn();
    if ($naechsterId) {
        header('Location: scan.php?modus=auftrag&auftrag_id=' . $naechsterId . '&pickliste_id=' . $picklisteId);
        exit;
    }
}

header('Location: index.php');
exit;
