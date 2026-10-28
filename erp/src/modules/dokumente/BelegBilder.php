<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * BelegBilder – Artikelbilder auf Belegen und Mails (Jacky 2026-10-28)
 *
 * Je Belegtyp einschaltbar (system_einstellungen beleg_bilder_<typ>, Einstellungen → System).
 * Feste Größe (12 mm im PDF), damit die Vorlagen nicht bei jeder Änderung nachgezogen werden.
 * Statt der Originale (Ø 85 KB) kommen kleine Vorschaubilder (150 px, JPEG) in die Belege,
 * zwischengespeichert unter storage/bild_vorschau/<artikel_id>/ — der Dateiname des Originals
 * steckt im Cache-Namen, ein neues Hauptbild erzeugt also automatisch eine neue Vorschau.
 * Kind-Artikel ohne eigenes Bild bekommen das Hauptbild des Vaters.
 */
class BelegBilder
{
    /** PDF-Belege => Bezeichnung in den Einstellungen */
    public const BELEGE = [
        'rechnung'             => 'Rechnung (A4)',
        'gutschrift'           => 'Rechnungskorrektur / Storno',
        'auftragsbestaetigung' => 'Auftragsbestätigung (PDF)',
        'lieferschein'         => 'Lieferschein',
        'pickliste'            => 'Pickliste (Packplatz)',
        'abholzettel'          => 'Abholzettel',
        'kassenbon_a4'         => 'Kassenbon A4',
    ];

    public const MAILS = [
        'mail_auftragsbestaetigung' => 'Auftragsbestätigung',
        'mail_abholung'             => 'Liegt zur Abholung bereit',
        'mail_versand'              => 'Versandbestätigung',
    ];

    private const PIXEL = 150;

    private static ?array $einstellungen = null;

    public static function aktiv(string $typ): bool
    {
        if (self::$einstellungen === null) {
            self::$einstellungen = Database::getInstance()->query("
                SELECT schluessel, wert FROM system_einstellungen WHERE schluessel LIKE 'beleg_bilder_%'
            ")->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        return (self::$einstellungen['beleg_bilder_' . $typ] ?? '0') === '1';
    }

    /**
     * Setzt 'bild_base64' (JPEG, '' = kein Bild) an jeder Position, wenn der Typ aktiv ist.
     * Die Positionen brauchen 'artikel_id'; ohne bleibt das Feld leer (z.B. Divers, Versand).
     */
    public static function anreichern(array $positionen, string $typ): array
    {
        if (!self::aktiv($typ) || !$positionen) return $positionen;

        $ids = array_values(array_unique(array_filter(array_map(fn($p) => (int)($p['artikel_id'] ?? 0), $positionen))));
        $bilder = self::hauptbilder($ids);
        foreach ($positionen as &$p) {
            $b = $bilder[(int)($p['artikel_id'] ?? 0)] ?? null;
            $p['bild_base64'] = $b ? (self::vorschau((int)$b['artikel_id'], $b['dateiname']) ?? '') : '';
        }
        unset($p);
        return $positionen;
    }

    /** artikel_id => ['artikel_id' => Bild-Besitzer (ggf. Vater), 'dateiname' => …] */
    private static function hauptbilder(array $ids): array
    {
        if (!$ids) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::getInstance()->prepare("
            SELECT a.id,
                   (SELECT CONCAT(b.artikel_id, '|', b.dateiname) FROM artikel_bilder b
                     WHERE b.artikel_id IN (a.id, a.vaterartikel_id)
                     ORDER BY b.artikel_id = a.id DESC, b.position, b.id LIMIT 1) AS bild
            FROM artikel a WHERE a.id IN ($ph)
        ");
        $stmt->execute($ids);
        $bilder = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (!$r['bild']) continue;
            [$besitzer, $datei] = explode('|', $r['bild'], 2);
            $bilder[(int)$r['id']] = ['artikel_id' => (int)$besitzer, 'dateiname' => $datei];
        }
        return $bilder;
    }

    /** Quadratisches Vorschaubild (weißer Rand) als Base64-JPEG, null wenn das Original fehlt */
    public static function vorschau(int $artikelId, string $dateiname): ?string
    {
        $datei    = basename($dateiname);
        $original = __DIR__ . '/../../../public/uploads/artikel/' . $artikelId . '/' . $datei;
        $cache    = __DIR__ . '/../../../storage/bild_vorschau/' . $artikelId . '/' . pathinfo($datei, PATHINFO_FILENAME) . '_' . self::PIXEL . '.jpg';

        if (!is_file($cache)) {
            if (!is_file($original)) return null;
            $quelle = @imagecreatefromstring((string)file_get_contents($original));
            if (!$quelle) return null;

            $w = imagesx($quelle); $h = imagesy($quelle);
            $faktor = min(self::PIXEL / $w, self::PIXEL / $h, 1);
            $nw = max(1, (int)round($w * $faktor)); $nh = max(1, (int)round($h * $faktor));
            $ziel = imagecreatetruecolor(self::PIXEL, self::PIXEL);
            imagefill($ziel, 0, 0, imagecolorallocate($ziel, 255, 255, 255)); // auch für transparente PNGs
            imagecopyresampled($ziel, $quelle, intdiv(self::PIXEL - $nw, 2), intdiv(self::PIXEL - $nh, 2), 0, 0, $nw, $nh, $w, $h);

            if (!is_dir(dirname($cache))) mkdir(dirname($cache), 0775, true);
            imagejpeg($ziel, $cache, 80);
            imagedestroy($quelle); imagedestroy($ziel);
        }
        return base64_encode((string)file_get_contents($cache));
    }
}
