<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/BilderRepository.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt']);
    exit;
}

$artikelId = (int)($_POST['artikel_id'] ?? 0);
if ($artikelId <= 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Artikel-ID']);
    exit;
}

if (empty($_FILES['bild']) || $_FILES['bild']['error'] !== UPLOAD_ERR_OK) {
    $fehlerCode = $_FILES['bild']['error'] ?? -1;
    echo json_encode(['erfolg' => false, 'fehler' => 'Upload-Fehler Code ' . $fehlerCode]);
    exit;
}

// Fehlende PHP-Erweiterungen sollen eine klare Fehlermeldung liefern statt eines
// unbehandelten Fatal Errors ("Call to undefined function") mitten in der
// JSON-Antwort — das Frontend zeigt sonst nur generisch "Netzwerkfehler".
if (!extension_loaded('gd')) {
    echo json_encode(['erfolg' => false, 'fehler' => 'PHP-GD-Erweiterung ist auf diesem Server nicht aktiviert.']);
    exit;
}
if (!extension_loaded('fileinfo')) {
    echo json_encode(['erfolg' => false, 'fehler' => 'PHP-Fileinfo-Erweiterung ist auf diesem Server nicht aktiviert.']);
    exit;
}

$file     = $_FILES['bild'];
$maxBytes = 10 * 1024 * 1024; // 10 MB

if ($file['size'] > $maxBytes) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Datei zu groß (max. 10 MB)']);
    exit;
}

// MIME-Typ prüfen (nicht nur Dateiendung)
$erlaubteMimes = ['image/jpeg', 'image/png', 'image/webp'];
$mimeTyp = mime_content_type($file['tmp_name']);
if (!in_array($mimeTyp, $erlaubteMimes)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur JPG, PNG und WEBP erlaubt']);
    exit;
}

// Upload-Ordner anlegen
$uploadDir = __DIR__ . '/../uploads/artikel/' . $artikelId . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Eindeutigen Dateinamen generieren
$endung    = 'jpg'; // Wir speichern immer als JPEG (außer PNG mit Transparenz)
$dateiname = 'bild_' . time() . '_' . random_int(1000, 9999) . '.' . $endung;
$zielpfad  = $uploadDir . $dateiname;

// Bild verkleinern und als JPEG speichern (PHP GD)
$verkleinert = verkleinereUndSpeichere($file['tmp_name'], $mimeTyp, $zielpfad);
if (!$verkleinert) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Bild konnte nicht verarbeitet werden']);
    exit;
}

// DB-Eintrag
$repo  = new BilderRepository();
$bildId = $repo->insert($artikelId, $dateiname);

echo json_encode([
    'erfolg'    => true,
    'bild_id'   => $bildId,
    'dateiname' => $dateiname,
    'url'       => BASE_PATH . '/uploads/artikel/' . $artikelId . '/' . $dateiname,
]);

// ─── Hilfsfunktion ───────────────────────────────────────────────────────────

function verkleinereUndSpeichere(string $tmpPfad, string $mimeTyp, string $zielpfad): bool
{
    $maxDimension = 1920;
    $jpegQualitaet = 85;

    // Quelldatei laden
    $quelle = match ($mimeTyp) {
        'image/jpeg' => imagecreatefromjpeg($tmpPfad),
        'image/png'  => imagecreatefrompng($tmpPfad),
        'image/webp' => imagecreatefromwebp($tmpPfad),
        default      => false,
    };

    if ($quelle === false) return false;

    $breite = imagesx($quelle);
    $hoehe  = imagesy($quelle);

    // Nur verkleinern wenn nötig
    if ($breite > $maxDimension || $hoehe > $maxDimension) {
        if ($breite >= $hoehe) {
            $neueBreite = $maxDimension;
            $neueHoehe  = (int)round($hoehe * $maxDimension / $breite);
        } else {
            $neueHoehe  = $maxDimension;
            $neueBreite = (int)round($breite * $maxDimension / $hoehe);
        }

        $ziel = imagecreatetruecolor($neueBreite, $neueHoehe);

        // Transparenz erhalten (PNG)
        if ($mimeTyp === 'image/png') {
            imagealphablending($ziel, false);
            imagesavealpha($ziel, true);
        }

        imagecopyresampled($ziel, $quelle, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);
        imagedestroy($quelle);
        $quelle = $ziel;
    }

    // PNG mit Transparenz als PNG speichern, sonst JPEG
    if ($mimeTyp === 'image/png') {
        $zielpfad = preg_replace('/\.jpg$/', '.png', $zielpfad);
        $ergebnis = imagepng($quelle, $zielpfad, 8);
    } else {
        $ergebnis = imagejpeg($quelle, $zielpfad, $jpegQualitaet);
    }

    imagedestroy($quelle);
    return $ergebnis;
}
