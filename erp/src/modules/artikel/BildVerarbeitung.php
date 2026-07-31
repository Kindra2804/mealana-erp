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
    public static function verkleinereUndSpeichere(string $tmpPfad, string $mimeTyp, string $zielpfad): bool
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
        return $ergebnis;
    }
}
