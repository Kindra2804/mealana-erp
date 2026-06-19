<?php
/**
 * Sammel-Handler für kleine Bild-Aktionen:
 * aktion=alt_text   — Alt-Text speichern
 * aktion=position   — Bild hoch/runter verschieben
 * aktion=hauptbild  — Bild zum Hauptbild machen
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/BilderRepository.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$aktion    = $_POST['aktion']     ?? '';
$bildId    = (int)($_POST['bild_id']    ?? 0);
$artikelId = (int)($_POST['artikel_id'] ?? 0);

if ($bildId <= 0 || $artikelId <= 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Parameter']);
    exit;
}

$repo = new BilderRepository();

// Sicherheitscheck: gehört das Bild zu diesem Artikel?
$bild = $repo->findById($bildId);
if (!$bild || (int)$bild['artikel_id'] !== $artikelId) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Bild nicht gefunden']);
    exit;
}

switch ($aktion) {
    case 'alt_text':
        $altText = trim($_POST['alt_text'] ?? '');
        $repo->updateAltText($bildId, $altText);
        echo json_encode(['erfolg' => true]);
        break;

    case 'position':
        $richtung = $_POST['richtung'] ?? '';
        if (!in_array($richtung, ['hoch', 'runter'])) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Richtung']);
            exit;
        }
        $repo->verschiebePosition($bildId, $artikelId, $richtung);
        echo json_encode(['erfolg' => true]);
        break;

    case 'hauptbild':
        $repo->setzeHauptbild($bildId, $artikelId);
        echo json_encode(['erfolg' => true]);
        break;

    default:
        echo json_encode(['erfolg' => false, 'fehler' => 'Unbekannte Aktion']);
}
