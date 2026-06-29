<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/modules/lager/LagerService.php';
require_once __DIR__ . '/../../../src/core/Logger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Anfrage']); exit;
}

$originalId = (int)($_POST['artikel_id']   ?? 0);
$menge      = (float)($_POST['menge']      ?? 0);
$zustand    = trim($_POST['zustand']       ?? '');
$vonLagerId = (int)($_POST['von_lager_id'] ?? 0);
$zuLagerId  = (int)($_POST['zu_lager_id']  ?? 0);
$charge     = !empty($_POST['charge']) ? trim($_POST['charge']) : null;
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

$erlaubteZustaende = ['neu','gebraucht','generalueberholt','beschaedigt','retour','demo','muster','ausstellungsstueck'];

if (!$originalId || $menge <= 0 || !$vonLagerId || !$zuLagerId || !in_array($zustand, $erlaubteZustaende, true)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Daten']); exit;
}

$db         = Database::getInstance();
$lagerSvc   = new LagerService();

// ── Artikel laden ─────────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM artikel WHERE id = :id");
$stmt->execute([':id' => $originalId]);
$orig = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$orig) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Artikel nicht gefunden']); exit;
}

// ── Rückbuchung: zustand='neu' → zurück zum Vater-Artikel ────────────────
if ($zustand === 'neu') {
    if (!$orig['zustand_vater_id']) {
        echo json_encode(['erfolg' => false, 'fehler' => 'Dieser Artikel ist kein Zustandsartikel — Rückbuchung nicht möglich.']); exit;
    }
    $vaterId = (int)$orig['zustand_vater_id'];

    $bStmt = $db->prepare("SELECT COALESCE(SUM(bestand),0) FROM lagerbestand WHERE artikel_id = :aid AND lager_id = :lid");
    $bStmt->execute([':aid' => $originalId, ':lid' => $vonLagerId]);
    $bestand = (float)$bStmt->fetchColumn();
    if ($bestand < $menge) {
        echo json_encode(['erfolg' => false, 'fehler' => 'Nicht genug Bestand (verfügbar: ' . (int)$bestand . ')']); exit;
    }

    $refText = 'Rückbuchung → Normal';
    $ausgang = $lagerSvc->warenausgang(['artikel_id' => $originalId, 'lager_id' => $vonLagerId, 'menge' => $menge, 'charge' => $charge, 'referenz' => $refText, 'benutzer_id' => $benutzerId]);
    if (!($ausgang['erfolg'] ?? false)) {
        echo json_encode(['erfolg' => false, 'fehler' => $ausgang['fehler'] ?? 'Ausgang fehlgeschlagen']); exit;
    }
    $eingang = $lagerSvc->wareneingang(['artikel_id' => $vaterId, 'lager_id' => $zuLagerId, 'menge' => $menge, 'charge' => $charge, 'referenz' => $refText, 'benutzer_id' => $benutzerId]);
    if (!($eingang['erfolg'] ?? false)) {
        echo json_encode(['erfolg' => false, 'fehler' => $eingang['fehler'] ?? 'Eingang fehlgeschlagen']); exit;
    }

    $vStmt = $db->prepare("SELECT artikelnummer, name FROM artikel WHERE id = :id");
    $vStmt->execute([':id' => $vaterId]);
    $vInfo = $vStmt->fetch(PDO::FETCH_ASSOC);

    Logger::log('lager.zustandsrueckbuchung', 'artikel', $originalId, ['menge' => $menge, 'vater_id' => $vaterId], $benutzerId);

    echo json_encode(['erfolg' => true, 'neu_angelegt' => false, 'zs_nr' => $vInfo['artikelnummer'], 'zs_name' => $vInfo['name']]);
    exit;
}

// Bestand prüfen (normaler Weg)
$bStmt = $db->prepare("SELECT COALESCE(SUM(bestand),0) FROM lagerbestand WHERE artikel_id = :aid AND lager_id = :lid");
$bStmt->execute([':aid' => $originalId, ':lid' => $vonLagerId]);
$bestand = (float)$bStmt->fetchColumn();
if ($bestand < $menge) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nicht genug Bestand (verfügbar: ' . (int)$bestand . ')']); exit;
}

// ── Zustandsartikel finden oder anlegen ──────────────────────────────────
$zsStmt = $db->prepare("SELECT id FROM artikel WHERE zustand_vater_id = :vid AND zustand = :z LIMIT 1");
$zsStmt->execute([':vid' => $originalId, ':z' => $zustand]);
$zustandsArtikelId = (int)($zsStmt->fetchColumn() ?: 0);

$zustandSuffixe = [
    'gebraucht'          => 'GEB',
    'generalueberholt'   => 'GEN',
    'beschaedigt'        => 'BSC',
    'retour'             => 'RET',
    'demo'               => 'DEMO',
    'muster'             => 'MST',
    'ausstellungsstueck' => 'AUS',
];
$zustandLabels = [
    'gebraucht'          => 'Gebraucht',
    'generalueberholt'   => 'Generalüberholt',
    'beschaedigt'        => 'Beschädigt',
    'retour'             => 'Retour',
    'demo'               => 'Demo',
    'muster'             => 'Muster',
    'ausstellungsstueck' => 'Ausstellungsstück',
];

$neuAngelegt = false;
if (!$zustandsArtikelId) {
    $suffix = $zustandSuffixe[$zustand];
    $neueNr = $orig['artikelnummer'] . '-' . $suffix;

    // Kollision mit fremdem Artikel vermeiden
    $checkStmt = $db->prepare("SELECT id FROM artikel WHERE artikelnummer = :nr AND (zustand_vater_id != :vid OR zustand_vater_id IS NULL)");
    $checkStmt->execute([':nr' => $neueNr, ':vid' => $originalId]);
    if ($checkStmt->fetchColumn()) {
        $neueNr = $orig['artikelnummer'] . '-' . $suffix . '-' . $originalId;
    }

    $neuName = $orig['name'] . ' (' . $zustandLabels[$zustand] . ')';

    $ins = $db->prepare("
        INSERT INTO artikel (
            artikelnummer, name, zustand, zustand_vater_id,
            steuerklasse_id, artikeltyp_id, hersteller_id, einheit_id,
            hat_eigenen_lagerstand, aktiv, ist_vater,
            inhalt_menge, inhalt_einheit,
            gewicht_artikel, gewicht_versand,
            charge_pflicht
        ) VALUES (
            :nr, :name, :zustand, :zvid,
            :sklid, :atid, :hid, :eid,
            1, 1, 0,
            :imenge, :ieinheit,
            :gewart, :gewvers,
            :cpflicht
        )
    ");
    $ins->execute([
        ':nr'       => $neueNr,
        ':name'     => $neuName,
        ':zustand'  => $zustand,
        ':zvid'     => $originalId,
        ':sklid'    => $orig['steuerklasse_id'],
        ':atid'     => $orig['artikeltyp_id'],
        ':hid'      => $orig['hersteller_id'],
        ':eid'      => $orig['einheit_id'],
        ':imenge'   => $orig['inhalt_menge'],
        ':ieinheit' => $orig['inhalt_einheit'],
        ':gewart'   => $orig['gewicht_artikel'],
        ':gewvers'  => $orig['gewicht_versand'],
        ':cpflicht' => $orig['charge_pflicht'] ?? 0,
    ]);
    $zustandsArtikelId = (int)$db->lastInsertId();
    $neuAngelegt = true;

    Logger::log('artikel.zustandsartikel_angelegt', 'artikel', $zustandsArtikelId, [
        'vater_id'   => $originalId,
        'zustand'    => $zustand,
        'artikelnr'  => $neueNr,
    ], $benutzerId);
}

// ── Ausgang vom Original + Eingang zum Zustandsartikel ───────────────────
$refText = 'Zustandsumbuchung → ' . $zustandLabels[$zustand];

$ausgang = $lagerSvc->warenausgang([
    'artikel_id'  => $originalId,
    'lager_id'    => $vonLagerId,
    'menge'       => $menge,
    'charge'      => $charge,
    'referenz'    => $refText,
    'benutzer_id' => $benutzerId,
]);
if (!($ausgang['erfolg'] ?? false)) {
    echo json_encode(['erfolg' => false, 'fehler' => $ausgang['fehler'] ?? 'Ausgang fehlgeschlagen']); exit;
}

$eingang = $lagerSvc->wareneingang([
    'artikel_id'  => $zustandsArtikelId,
    'lager_id'    => $zuLagerId,
    'menge'       => $menge,
    'charge'      => $charge,
    'referenz'    => $refText,
    'benutzer_id' => $benutzerId,
]);
if (!($eingang['erfolg'] ?? false)) {
    echo json_encode(['erfolg' => false, 'fehler' => $eingang['fehler'] ?? 'Eingang fehlgeschlagen']); exit;
}

Logger::log('lager.zustandsumbuchung', 'artikel', $originalId, [
    'menge'              => $menge,
    'zustand'            => $zustand,
    'zustandsartikel_id' => $zustandsArtikelId,
    'neu_angelegt'       => $neuAngelegt,
], $benutzerId);

$stmt = $db->prepare("SELECT artikelnummer, name FROM artikel WHERE id = :id");
$stmt->execute([':id' => $zustandsArtikelId]);
$zsInfo = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'erfolg'      => true,
    'neu_angelegt'=> $neuAngelegt,
    'zs_nr'       => $zsInfo['artikelnummer'],
    'zs_name'     => $zsInfo['name'],
]);
