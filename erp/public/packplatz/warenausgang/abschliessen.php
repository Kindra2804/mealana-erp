<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/core/Logger.php';
require_once __DIR__ . '/../../../src/core/Mailer.php';
require_once __DIR__ . '/../../../src/core/EasyPakExporter.php';
require_once __DIR__ . '/../../../src/modules/lager/LagerService.php';
require_once __DIR__ . '/../../../src/modules/auftraege/AuftragRepository.php';

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

    // Lieferstatus + Tracking speichern (letzten Wert im Hauptdatensatz + History)
    $neuerStatus = $istTeillieferung ? 'teilgeliefert' : 'versendet';
    $db->prepare("
        UPDATE auftraege
        SET tracking_nr = ?, versanddienstleister = ?,
            versand_tracking = ?, versand_datum = NOW(),
            lieferstatus = ?, aktualisiert_am = NOW()
        WHERE id = ?
    ")->execute([$tracking, $versanddienstleister, $tracking, $neuerStatus, $auftragId]);

    // Lieferhistory-Eintrag
    $db->prepare("
        INSERT INTO auftrag_lieferungen
            (auftrag_id, tracking_nr, versanddienstleister, ist_teillieferung, benutzer_id)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$auftragId, $tracking, $versanddienstleister ?: null, $istTeillieferung ? 1 : 0, $benutzerId]);

    // Reservierungen schließen
    $auftragRepo->schliesseReservierungen($auftragId);

    // Statuslog korrekt schreiben
    $notizText = $istTeillieferung
        ? "Teillieferung versendet — Tracking: {$tracking}"
        : "Versendet — Tracking: {$tracking}";
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

// ─── Versandmail ──────────────────────────────────────────────────────────────
$kunden = json_decode($auftrag['kunden_snapshot'] ?? '{}', true);
if (!empty($auftrag['lieferadresse_snapshot'])) {
    $lieferAdr = json_decode($auftrag['lieferadresse_snapshot'], true);
} elseif (!empty($auftrag['rechnungsadresse_snapshot'])) {
    $lieferAdr = json_decode($auftrag['rechnungsadresse_snapshot'], true);
} else {
    $lieferAdr = json_decode($auftrag['kunden_snapshot'] ?? '{}', true);
}
$email = $kunden['email'] ?? '';
$name  = trim(($kunden['vorname'] ?? '') . ' ' . ($kunden['nachname'] ?? '')) ?: ($kunden['firma'] ?? '');

if ($email && !$istTeillieferung) {
    try {
        $firma = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);
        $mailer = new Mailer();
        $mailer->sendeTemplate(
            empfaenger:   $email,
            betreff:      'Ihr Auftrag ' . $auftrag['auftrag_nr'] . ' wurde versendet',
            templatePfad: 'mails/versandbestaetigung.html.twig',
            variablen: [
                'kunde_name'     => $name,
                'auftrag_nummer' => $auftrag['auftrag_nr'],
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

if ($email && $auftrag['lieferart'] === 'abholung') {
    try {
        $firma  = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);
        $mailer = new Mailer();
        $mailer->sende(
            empfaenger: $email,
            betreff:    'Ihre Bestellung ' . $auftrag['auftrag_nr'] . ' liegt zur Abholung bereit',
            htmlBody:   '<p>Sehr geehrte/r ' . htmlspecialchars($name) . ',</p>'
                . '<p>Ihre Bestellung <strong>' . htmlspecialchars($auftrag['auftrag_nr']) . '</strong> '
                . 'liegt ab sofort zur Abholung bei uns bereit.</p>'
                . '<p>Mit freundlichen Grüßen,<br>' . htmlspecialchars($firma['firmenname'] ?? 'MEALANA KG') . '</p>'
        );
    } catch (Throwable $e) {
        error_log('[Abholmail] Fehler: ' . $e->getMessage());
    }
}

// ─── Weiterleitung ────────────────────────────────────────────────────────────
if ($picklisteId) {
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
