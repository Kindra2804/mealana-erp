<?php

/**
 * BildVerarbeitung – GD-basierte Verkleinerung/Speicherung für Artikel-Bilder
 *
 * Extrahiert aus public/artikel/bild_upload.php (manueller Einzel-Upload), damit der
 * JTL-Bilder-Import (public/artikel/jtl_bilder_import.php) dieselbe Pipeline nutzt statt
 * die GD-Logik zu duplizieren. Verhalten unverändert: max. 1920px, JPEG 85%, PNG behält
 * Transparenz.
 */
class BildVerarbeitung
{
    public const ERLAUBTE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Lädt $tmpPfad, verkleinert bei Bedarf auf max. 1920px und speichert unter $zielpfad. */
    public static function verkleinereUndSpeichere(string $tmpPfad, string $mimeTyp, string $zielpfad): string|false
    {
        $maxDimension  = 1920;
        $jpegQualitaet = 85;

        $quelle = match ($mimeTyp) {
            'image/jpeg' => imagecreatefromjpeg($tmpPfad),
            'image/png'  => imagecreatefrompng($tmpPfad),
            'image/webp' => imagecreatefromwebp($tmpPfad),
            default      => false,
        };

        if ($quelle === false) return false;
        $quelle = self::richteAus($quelle, $tmpPfad, $mimeTyp);

        $breite = imagesx($quelle);
        $hoehe  = imagesy($quelle);

        if ($breite > $maxDimension || $hoehe > $maxDimension) {
            if ($breite >= $hoehe) {
                $neueBreite = $maxDimension;
                $neueHoehe  = (int) round($hoehe * $maxDimension / $breite);
            } else {
                $neueHoehe  = $maxDimension;
                $neueBreite = (int) round($breite * $maxDimension / $hoehe);
            }

            $ziel = imagecreatetruecolor($neueBreite, $neueHoehe);

            if ($mimeTyp === 'image/png') {
                imagealphablending($ziel, false);
                imagesavealpha($ziel, true);
            }

            imagecopyresampled($ziel, $quelle, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);
            imagedestroy($quelle);
            $quelle = $ziel;
        }

        if ($mimeTyp === 'image/png') {
            $zielpfad = preg_replace('/\.jpg$/', '.png', $zielpfad);
            $ergebnis = imagepng($quelle, $zielpfad, 8);
        } else {
            $ergebnis = imagejpeg($quelle, $zielpfad, $jpegQualitaet);
        }

        imagedestroy($quelle);
        if ($ergebnis) {
            return basename($zielpfad);
        } else {
            return false;
        }
    }

    /**
     * Dreht/spiegelt ein JPEG nach seinem EXIF-Vermerk „Orientation“ (2026-11-12).
     *
     * Handys speichern die Pixel immer in Sensor-Lage und vermerken die Drehung nur in EXIF.
     * GD ignoriert diesen Vermerk, und beim Neu-Speichern geht er verloren → hochkant
     * fotografierte Bilder lagen dann um 90° verdreht im ERP und im Shop.
     * Ohne exif-Erweiterung oder ohne Vermerk bleibt das Bild unverändert.
     */
    public static function richteAus(\GdImage $bild, string $pfad, string $mimeTyp): \GdImage
    {
        if ($mimeTyp !== 'image/jpeg' || !function_exists('exif_read_data')) return $bild;
        $exif = @exif_read_data($pfad);
        $o = (int)($exif['Orientation'] ?? 1);
        if ($o <= 1 || $o > 8) return $bild;

        $drehung = match ($o) { 3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0 }; // imagerotate: Grad gegen den Uhrzeigersinn
        if ($drehung !== 0) {
            $gedreht = imagerotate($bild, $drehung, 0);
            if ($gedreht !== false) { imagedestroy($bild); $bild = $gedreht; }
        }
        if (in_array($o, [2, 5, 7], true)) imageflip($bild, IMG_FLIP_HORIZONTAL);
        if ($o === 4) imageflip($bild, IMG_FLIP_HORIZONTAL); // 180° + waagrecht = senkrecht spiegeln
        return $bild;
    }
}
