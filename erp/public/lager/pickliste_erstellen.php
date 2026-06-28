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

$ersteller = $db->prepare("SELECT formularname FROM benutzer WHERE id = :id");
$ersteller->execute([':id' => $benutzerId]);
$erstellerName = $ersteller->fetchColumn() ?: 'System';

$pdf = new PdfGenerator();

$storagePfad = __DIR__ . '/../../storage/picklisten';
if (!is_dir($storagePfad)) mkdir($storagePfad, 0755, true);

$neueIds = [];

// Positionen-Statement vorbereiten
$posStmt = $db->prepare("
    SELECT p.id, p.bezeichnung, p.menge, p.artikel_id, ar.artikelnummer
    FROM auftrag_positionen p
    LEFT JOIN artikel ar ON ar.id = p.artikel_id
    WHERE p.auftrag_id = :id
    ORDER BY p.sort_order, p.id
");

// Auftragsdaten laden (alle auf einmal für Reihenfolge)
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

foreach ($auftraege as $auftrag) {
    $posStmt->execute([':id' => $auftrag['id']]);
    $positionen = $posStmt->fetchAll(PDO::FETCH_ASSOC);

    $auftragMitPos              = $auftrag;
    $auftragMitPos['positionen'] = $positionen;
    $auftragMitPos['kunde']      = json_decode($auftrag['kunden_snapshot'] ?? '{}', true) ?: [];

    // PL-Nummer und Pickliste in DB
    $plNummer = $repo->naechsteNummer('pickliste', (int)date('Y'));

    $db->beginTransaction();
    try {
        $db->prepare("
            INSERT INTO picklisten (nummer, status, erstellt_von, erstellt_am)
            VALUES (:nr, 'offen', :uid, NOW())
        ")->execute([':nr' => $plNummer, ':uid' => $benutzerId]);

        $plId = (int)$db->lastInsertId();

        $db->prepare("
            INSERT IGNORE INTO pickliste_auftraege (pickliste_id, auftrag_id)
            VALUES (:plid, :aid)
        ")->execute([':plid' => $plId, ':aid' => $auftrag['id']]);

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['fehler'] = 'Fehler beim Speichern: ' . $e->getMessage();
        header('Location: /mealana/lager/picklisten.php');
        exit;
    }

    // PDF generieren
    $dateiname = $plNummer . '.pdf';
    $dateipfad = $storagePfad . '/' . $dateiname;

    $barcode = $pdf->barcodeAlsBase64($plNummer);
    $pdf->generiere('pickliste/standard.html.twig', [
        'pl_nummer'          => $plNummer,
        'datum'              => date('d.m.Y'),
        'uhrzeit'            => date('H:i'),
        'erstellt_von'       => $erstellerName,
        'auftraege'          => [$auftragMitPos],
        'barcode'            => $barcode,
        'gesamt_positionen'  => count($positionen),
    ], $dateipfad);

    // Status auf 'gedruckt' setzen
    $db->prepare("UPDATE picklisten SET status = 'gedruckt' WHERE id = :id")
       ->execute([':id' => $plId]);

    $neueIds[] = $plId;
}

// Zurück — alle neuen Picklisten-IDs übergeben
header('Location: /mealana/lager/picklisten.php?neu=' . implode(',', $neueIds));
exit;
