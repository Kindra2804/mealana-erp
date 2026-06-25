<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/dokumente/DokumentRepository.php';
require_once __DIR__ . '/../../src/modules/dokumente/PdfGenerator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mealana/lager/picklisten.php');
    exit;
}

$auftragIds = array_map('intval', $_POST['auftrag_ids'] ?? []);
$auftragIds = array_filter($auftragIds, fn($id) => $id > 0);

if (empty($auftragIds)) {
    $_SESSION['fehler'] = 'Bitte mindestens einen Auftrag auswählen.';
    header('Location: /mealana/lager/picklisten.php');
    exit;
}

$db         = Database::getInstance();
$repo       = new DokumentRepository();
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);

// ── Nächste PL-Nummer ───────────────────────────────────────────────────────
$plNummer = $repo->naechsteNummer('pickliste', (int)date('Y'));

// ── Auftragsdaten laden ─────────────────────────────────────────────────────
$placeholders = implode(',', array_fill(0, count($auftragIds), '?'));
$stmt = $db->prepare("
    SELECT a.id, a.auftrag_nr, a.kunden_snapshot, a.lieferadresse_snapshot,
           a.zahlungsart, a.lieferart, a.erstellt_am
    FROM auftraege a
    WHERE a.id IN ($placeholders)
    ORDER BY a.erstellt_am ASC
");
$stmt->execute(array_values($auftragIds));
$auftraege = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($auftraege)) {
    $_SESSION['fehler'] = 'Aufträge nicht gefunden.';
    header('Location: /mealana/lager/picklisten.php');
    exit;
}

// Positionen je Auftrag laden
$posStmt = $db->prepare("
    SELECT p.id, p.bezeichnung, p.menge, p.artikel_id, ar.artikelnummer
    FROM auftrag_positionen p
    LEFT JOIN artikel ar ON ar.id = p.artikel_id
    WHERE p.auftrag_id = :id
    ORDER BY p.sort_order, p.id
");
foreach ($auftraege as &$a) {
    $posStmt->execute([':id' => $a['id']]);
    $a['positionen'] = $posStmt->fetchAll(PDO::FETCH_ASSOC);
    $a['kunde']      = json_decode($a['kunden_snapshot'] ?? '{}', true) ?: [];
}
unset($a);

// ── Pickliste in DB speichern ───────────────────────────────────────────────
$db->beginTransaction();
try {
    $db->prepare("
        INSERT INTO picklisten (nummer, status, erstellt_von, erstellt_am)
        VALUES (:nr, 'offen', :uid, NOW())
    ")->execute([':nr' => $plNummer, ':uid' => $benutzerId]);

    $plId = (int)$db->lastInsertId();

    $paStmt = $db->prepare("
        INSERT IGNORE INTO pickliste_auftraege (pickliste_id, auftrag_id)
        VALUES (:plid, :aid)
    ");
    foreach ($auftraege as $a) {
        $paStmt->execute([':plid' => $plId, ':aid' => $a['id']]);
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['fehler'] = 'Fehler beim Speichern: ' . $e->getMessage();
    header('Location: /mealana/lager/picklisten.php');
    exit;
}

// ── PDF generieren ──────────────────────────────────────────────────────────
$storagePfad = __DIR__ . '/../../storage/picklisten';
if (!is_dir($storagePfad)) mkdir($storagePfad, 0755, true);

$dateiname = $plNummer . '.pdf';
$dateipfad = $storagePfad . '/' . $dateiname;

$ersteller = $db->prepare("SELECT formularname FROM benutzer WHERE id = :id");
$ersteller->execute([':id' => $benutzerId]);
$erstellerName = $ersteller->fetchColumn() ?: 'System';

$pdf = new PdfGenerator();
$barcode = $pdf->barcodeAlsBase64($plNummer);

$pdf->generiere('pickliste/standard.html.twig', [
    'pl_nummer'    => $plNummer,
    'datum'        => date('d.m.Y'),
    'uhrzeit'      => date('H:i'),
    'erstellt_von' => $erstellerName,
    'auftraege'    => $auftraege,
    'barcode'      => $barcode,
    'gesamt_positionen' => array_sum(array_map(fn($a) => count($a['positionen']), $auftraege)),
], $dateipfad);

// Status auf 'gedruckt' setzen
$db->prepare("UPDATE picklisten SET status = 'gedruckt' WHERE id = :id")
   ->execute([':id' => $plId]);

// ── PDF sofort zum Download ─────────────────────────────────────────────────
header('Location: /mealana/lager/pickliste_pdf.php?id=' . $plId);
exit;
