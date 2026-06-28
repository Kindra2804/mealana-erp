<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';
require_once __DIR__ . '/../../src/core/Database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => ['Nur POST erlaubt']]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['erfolg' => false, 'fehler' => ['ID fehlt']]);
    exit;
}

$service = new AuftragService();

$felder = [];
$erlaubt = ['zahlungsstatus', 'lieferstatus', 'tracking_nr', 'versanddienstleister', 'notiz_intern', 'notiz_versand'];
foreach ($erlaubt as $f) {
    if (isset($_POST[$f])) {
        $felder[$f] = $_POST[$f];
    }
}

$notiz    = !empty($_POST['notiz']) ? trim($_POST['notiz']) : null;
$ergebnis = $service->statusAktualisieren($id, $felder, $notiz);

// Wenn Tracking manuell eingetragen → History-Eintrag anlegen
if ($ergebnis['erfolg'] && !empty($felder['tracking_nr'])) {
    $benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);
    $db = Database::getInstance();

    // Altes tracking_nr aus auftraege.tracking_nr in History migrieren,
    // falls diese Tabelle noch leer ist (Einträge vor Migration 087)
    $countStmt = $db->prepare("SELECT COUNT(*) FROM auftrag_lieferungen WHERE auftrag_id = ?");
    $countStmt->execute([$id]);
    if ((int)$countStmt->fetchColumn() === 0) {
        $altStmt = $db->prepare("SELECT tracking_nr, versand_tracking, versanddienstleister, versand_datum FROM auftraege WHERE id = ?");
        $altStmt->execute([$id]);
        $alt = $altStmt->fetch(PDO::FETCH_ASSOC);
        $altTracking = $alt['tracking_nr'] ?: ($alt['versand_tracking'] ?? '');
        if ($altTracking && $altTracking !== $felder['tracking_nr']) {
            $db->prepare("
                INSERT INTO auftrag_lieferungen
                    (auftrag_id, tracking_nr, versanddienstleister, versand_datum, ist_teillieferung, benutzer_id)
                VALUES (?, ?, ?, ?, 0, NULL)
            ")->execute([$id, $altTracking, $alt['versanddienstleister'] ?? null, $alt['versand_datum'] ?? date('Y-m-d H:i:s')]);
        }
    }

    $db->prepare("
        INSERT INTO auftrag_lieferungen
            (auftrag_id, tracking_nr, versanddienstleister, ist_teillieferung, benutzer_id)
        VALUES (?, ?, ?, 0, ?)
    ")->execute([$id, $felder['tracking_nr'], $felder['versanddienstleister'] ?? null, $benutzerId]);
}

echo json_encode($ergebnis);
