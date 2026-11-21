<?php

/**
 * Zeichnet Produktbilder für die Demo mit GD (nur Formen, kein Text — auf einem Webspace
 * gibt es keine verlässlichen Schriften). 800 × 800 JPEG.
 */
class DemoBilder
{
    private const GROESSE = 800;

    /** Erzeugt das Bild und legt die Zeile in artikel_bilder an */
    public static function speichern(DemoBasis $b, int $artikelId, string $art, string $hex, string $muster = 'uni', string $alt = ''): void
    {
        $ordner = __DIR__ . '/../../public/uploads/artikel/' . $artikelId;
        if (!is_dir($ordner)) mkdir($ordner, 0775, true);
        $datei = 'demo_' . $artikelId . '.jpg';
        $img = self::zeichne($art, $hex, $muster);
        imagejpeg($img, $ordner . '/' . $datei, 82);
        imagedestroy($img);
        $b->ins('artikel_bilder', ['artikel_id' => $artikelId, 'dateiname' => $datei, 'alt_text' => $alt, 'position' => 0]);
    }

    private static function zeichne(string $art, string $hex, string $muster)
    {
        $s = self::GROESSE;
        $img = imagecreatetruecolor($s, $s);
        // Kein imageantialias(): damit ignoriert GD imagesetthickness() (Nadeln wären haarfein)
        [$r, $g, $bl] = self::rgb($hex);
        $hg = imagecolorallocate($img, 246, 243, 238);
        imagefill($img, 0, 0, $hg);

        match ($art) {
            'garn'     => self::knaeuel($img, $r, $g, $bl, $muster),
            'stoff'    => self::stoff($img, $r, $g, $bl, $muster),
            'nadel'    => self::nadeln($img, $r, $g, $bl),
            'haekel'   => self::haekelnadel($img, $r, $g, $bl),
            'knopf'    => self::knoepfe($img, $r, $g, $bl),
            'buch'     => self::buch($img, $r, $g, $bl),
            'marker'   => self::marker($img),
            'massband' => self::massband($img, $r, $g, $bl),
            'tasche'   => self::tasche($img, $r, $g, $bl),
            default    => null,
        };
        return $img;
    }

    private static function knaeuel($img, int $r, int $g, int $b, string $muster): void
    {
        $s = self::GROESSE; $cx = $s / 2; $cy = $s / 2 + 20; $rad = 270;
        // Schatten
        imagefilledellipse($img, $cx + 10, $cy + $rad - 10, 2 * $rad - 60, 60, imagecolorallocate($img, 225, 220, 212));
        imagefilledellipse($img, $cx, $cy, 2 * $rad, 2 * $rad, self::farbe($img, $r, $g, $b));
        $farben = $muster === 'ringel'
            ? [[$r, $g, $b], [255 - (int)($r / 2), 200, 120], [60, 120, 180], [230, 230, 220]]
            : [[$r, $g, $b]];
        imagesetthickness($img, 7);
        // Fadenbögen in mehreren Richtungen
        for ($schicht = 0; $schicht < 3; $schicht++) {
            for ($i = 0; $i < 13; $i++) {
                $f = $farben[($i + $schicht) % count($farben)];
                $dunkel = self::farbe($img, ...self::abdunkeln($f, 0.72 + 0.04 * ($i % 3)));
                $w = 2 * $rad - 30 - $i * 34;
                if ($w < 40) break;
                $start = 200 + $schicht * 60; $ende = $start + 150;
                imagearc($img, (int)($cx - 40 + $schicht * 40), $cy, $w, (int)($w * (0.55 + 0.15 * $schicht)), $start, $ende, $dunkel);
            }
        }
        if ($muster === 'tweed' || $muster === 'flausch') {
            for ($i = 0; $i < ($muster === 'tweed' ? 160 : 400); $i++) {
                $wink = mt_rand(0, 628) / 100; $ab = mt_rand(0, $rad - 15);
                $x = (int)($cx + cos($wink) * $ab); $y = (int)($cy + sin($wink) * $ab);
                $c = $muster === 'tweed'
                    ? self::farbe($img, mt_rand(150, 240), mt_rand(120, 220), mt_rand(80, 200))
                    : self::farbe($img, ...self::aufhellen([$r, $g, $b], 0.35));
                imagefilledellipse($img, $x, $y, $muster === 'tweed' ? 7 : 4, $muster === 'tweed' ? 7 : 4, $c);
            }
        }
        // Glanzlicht + loser Faden
        imagefilledellipse($img, $cx - 110, $cy - 120, 120, 70, self::farbe($img, ...self::aufhellen([$r, $g, $b], 0.25)));
        imagesetthickness($img, 8);
        $faden = self::farbe($img, ...self::abdunkeln([$r, $g, $b], 0.85));
        imagearc($img, $cx + 240, $cy + 210, 240, 160, 300, 80, $faden);
        imagesetthickness($img, 1);
    }

    private static function stoff($img, int $r, int $g, int $b, string $muster): void
    {
        $s = self::GROESSE; $m = 70;
        $grund = self::farbe($img, $r, $g, $b);
        $hell  = imagecolorallocate($img, 250, 248, 244);
        imagefilledrectangle($img, $m, $m, $s - $m, $s - $m, $muster === 'tupfen' ? $hell : $grund);
        switch ($muster) {
            case 'tupfen':
                imagefilledrectangle($img, $m, $m, $s - $m, $s - $m, $grund);
                for ($y = $m + 30; $y < $s - $m; $y += 60) for ($x = $m + 30 + (($y / 60) % 2) * 30; $x < $s - $m; $x += 60)
                    imagefilledellipse($img, $x, $y, 22, 22, $hell);
                break;
            case 'karo':
                imagefilledrectangle($img, $m, $m, $s - $m, $s - $m, $hell);
                $halb = imagecolorallocatealpha($img, $r, $g, $b, 60);
                for ($i = $m; $i < $s - $m; $i += 80) {
                    imagefilledrectangle($img, $i, $m, $i + 40, $s - $m, $halb);
                    imagefilledrectangle($img, $m, $i, $s - $m, $i + 40, $halb);
                }
                break;
            case 'streifen':
                imagefilledrectangle($img, $m, $m, $s - $m, $s - $m, $hell);
                for ($y = $m; $y < $s - $m; $y += 50) imagefilledrectangle($img, $m, $y, $s - $m, $y + 24, $grund);
                break;
            case 'krepp':
                for ($i = 0; $i < 90; $i++) {
                    $y = mt_rand($m, $s - $m);
                    imageline($img, $m, $y, $s - $m, $y + mt_rand(-8, 8), self::farbe($img, ...self::abdunkeln([$r, $g, $b], 0.92)));
                }
                break;
            default: // uni: feine Webstruktur
                $f = self::farbe($img, ...self::abdunkeln([$r, $g, $b], 0.93));
                for ($i = $m; $i < $s - $m; $i += 6) imageline($img, $i, $m, $i, $s - $m, $f);
        }
        // umgeschlagene Ecke + Schatten
        $schatten = imagecolorallocatealpha($img, 0, 0, 0, 100);
        imagefilledpolygon($img, [$s - $m, $s - $m - 200, $s - $m, $s - $m, $s - $m - 200, $s - $m], $schatten);
        imagefilledpolygon($img, [$s - $m - 200, $s - $m, $s - $m, $s - $m - 200, $s - $m - 170, $s - $m - 30], self::farbe($img, ...self::abdunkeln([$r, $g, $b], 0.8)));
    }

    private static function nadeln($img, int $r, int $g, int $b): void
    {
        $farbe = self::farbe($img, $r, $g, $b); $dunkel = self::farbe($img, ...self::abdunkeln([$r, $g, $b], 0.7));
        imagesetthickness($img, 26);
        imageline($img, 170, 650, 610, 140, $farbe);
        imageline($img, 230, 680, 650, 190, $farbe);
        imagesetthickness($img, 1);
        imagefilledellipse($img, 170, 650, 50, 50, $dunkel);
        imagefilledellipse($img, 230, 680, 50, 50, $dunkel);
        // Seil der Rundnadel
        imagesetthickness($img, 8);
        imagearc($img, 400, 640, 520, 260, 0, 180, self::farbe($img, 120, 120, 130));
        imagesetthickness($img, 1);
    }

    private static function haekelnadel($img, int $r, int $g, int $b): void
    {
        imagesetthickness($img, 18);
        imageline($img, 200, 620, 620, 180, imagecolorallocate($img, 190, 195, 200));
        imagesetthickness($img, 60);
        imageline($img, 160, 660, 380, 430, self::farbe($img, $r, $g, $b));
        imagesetthickness($img, 1);
        imagefilledellipse($img, 630, 170, 34, 34, imagecolorallocate($img, 160, 165, 170));
    }

    private static function knoepfe($img, int $r, int $g, int $b): void
    {
        $pos = [[260, 260], [540, 260], [260, 540], [540, 540], [400, 400]];
        foreach ($pos as [$x, $y]) {
            imagefilledellipse($img, $x + 8, $y + 10, 220, 220, imagecolorallocate($img, 220, 214, 205));
            imagefilledellipse($img, $x, $y, 220, 220, self::farbe($img, $r, $g, $b));
            imageellipse($img, $x, $y, 170, 170, self::farbe($img, ...self::abdunkeln([$r, $g, $b], 0.75)));
            foreach ([[-25, -25], [25, -25], [-25, 25], [25, 25]] as [$dx, $dy])
                imagefilledellipse($img, $x + $dx, $y + $dy, 22, 22, self::farbe($img, ...self::abdunkeln([$r, $g, $b], 0.55)));
        }
    }

    private static function buch($img, int $r, int $g, int $b): void
    {
        imagefilledrectangle($img, 215, 110, 625, 710, imagecolorallocate($img, 215, 208, 198));
        imagefilledrectangle($img, 200, 95, 610, 695, self::farbe($img, $r, $g, $b));
        imagefilledrectangle($img, 200, 95, 236, 695, self::farbe($img, ...self::abdunkeln([$r, $g, $b], 0.75)));
        $hell = imagecolorallocate($img, 248, 244, 236);
        imagefilledrectangle($img, 280, 180, 560, 205, $hell);
        imagefilledrectangle($img, 280, 225, 500, 245, $hell);
        // Knäuel-Symbol am Cover
        imagefilledellipse($img, 420, 470, 190, 190, $hell);
        imagesetthickness($img, 5);
        $c = self::farbe($img, $r, $g, $b);
        for ($i = 0; $i < 5; $i++) imagearc($img, 420, 470, 170 - $i * 30, 100 - $i * 15, 200, 340, $c);
        imagesetthickness($img, 1);
    }

    private static function marker($img): void
    {
        $farben = [[224, 80, 139], [43, 179, 177], [239, 216, 74], [93, 127, 209], [108, 170, 60], [240, 138, 53]];
        foreach ($farben as $i => $f) {
            $x = 200 + ($i % 3) * 200; $y = 280 + intdiv($i, 3) * 240;
            imagesetthickness($img, 18);
            imagearc($img, $x, $y, 130, 130, 0, 300, self::farbe($img, ...$f));
        }
        imagesetthickness($img, 1);
    }

    private static function massband($img, int $r, int $g, int $b): void
    {
        imagefilledellipse($img, 400, 400, 460, 460, self::farbe($img, $r, $g, $b));
        imagefilledellipse($img, 400, 400, 180, 180, imagecolorallocate($img, 246, 243, 238));
        $s = imagecolorallocate($img, 40, 40, 40);
        for ($a = 0; $a < 360; $a += 6) {
            $l = $a % 30 === 0 ? 60 : 30;
            imageline($img, (int)(400 + cos(deg2rad($a)) * 230), (int)(400 + sin(deg2rad($a)) * 230),
                            (int)(400 + cos(deg2rad($a)) * (230 - $l)), (int)(400 + sin(deg2rad($a)) * (230 - $l)), $s);
        }
    }

    private static function tasche($img, int $r, int $g, int $b): void
    {
        imagefilledrectangle($img, 200, 300, 600, 680, self::farbe($img, $r, $g, $b));
        imagesetthickness($img, 22);
        imagearc($img, 400, 300, 260, 300, 180, 360, self::farbe($img, ...self::abdunkeln([$r, $g, $b], 0.7)));
        imagesetthickness($img, 1);
        imagefilledellipse($img, 400, 520, 120, 120, imagecolorallocate($img, 79, 125, 192));
    }

    // ── Farben ────────────────────────────────────────────────────────────
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
    private static function farbe($img, int $r, int $g, int $b) { return imagecolorallocate($img, max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b))); }
    private static function abdunkeln(array $f, float $faktor): array { return array_map(fn($v) => (int)($v * $faktor), $f); }
    private static function aufhellen(array $f, float $anteil): array { return array_map(fn($v) => (int)($v + (255 - $v) * $anteil), $f); }
}
