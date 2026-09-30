<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/modules/lager/LagerService.php';
require_once __DIR__ . '/../../../src/core/Logger.php';
require_once __DIR__ . '/../../../src/modules/packplatz/RetourService.php';

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

// ── Zustandsartikel finden oder anlegen (gemeinsame Logik, siehe RetourService) ──
try {
    $zs = (new RetourService())->findeOderLegeZustandsartikelAn($originalId, $zustand, $benutzerId);
} catch (Throwable $e) {
    echo json_encode(["erfolg" => false, "fehler" => $e->getMessage()]); exit;
}
$zustandsArtikelId = $zs["id"];
$neuAngelegt       = $zs["neu_angelegt"];
$zustandLabels     = RetourService::ZUSTAND_LABELS;

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
