<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * PdfGenerator – Wandelt Twig-Templates in PDF-Dateien um.
 *
 * Bindet den Composer-Autoloader ein (Twig + Dompdf + Barcode-Library).
 * Templates liegen in erp/templates/dokumente/{typ}/standard.html.twig.
 * Fertige PDFs werden nach erp/storage/dokumente/{auftrag_id}/ geschrieben.
 */
class PdfGenerator
{
    private \Twig\Environment $twig;

    public function __construct()
    {
        require_once __DIR__ . '/../../../vendor/autoload.php';

        $loader = new \Twig\Loader\FilesystemLoader(
            __DIR__ . '/../../../templates/dokumente'
        );
        $this->twig = new \Twig\Environment($loader, [
            'autoescape' => 'html',
        ]);

        $this->twig->addFilter(new \Twig\TwigFilter(
            'euroformat',
            fn($v) => number_format((float)$v, 2, ',', '.')
        ));
    }

    /**
     * Rendert ein Twig-Template zu HTML und erzeugt daraus ein PDF.
     *
     * @param string $template  Relativer Pfad, z.B. "rechnung/standard.html.twig"
     * @param array  $daten     Variablen für das Template
     * @param string $dateipfad Absoluter Pfad der Zieldatei (inkl. .pdf)
     */
    public function generiere(string $template, array $daten, string $dateipfad): void
    {
        $html = $this->twig->render($template, $daten);

        $optionen = new \Dompdf\Options();
        $optionen->set('defaultFont', 'DejaVu Sans');
        $optionen->set('isRemoteEnabled', false);
        $optionen->set('isPhpEnabled', false);
        $optionen->setChroot(realpath(__DIR__ . '/../../../'));

        $dompdf = new \Dompdf\Dompdf($optionen);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $verzeichnis = dirname($dateipfad);
        if (!is_dir($verzeichnis)) {
            mkdir($verzeichnis, 0755, true);
        }

        file_put_contents($dateipfad, $dompdf->output());
    }

    /**
     * Erzeugt einen Code-128-Barcode als Base64-PNG für Twig-Templates.
     * Verwendung im Template: <img src="data:image/png;base64,{{ barcode }}">
     */
    public function barcodeAlsBase64(string $text): string
    {
        require_once __DIR__ . '/../../../vendor/autoload.php';
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        $png       = $generator->getBarcode($text, $generator::TYPE_CODE_128, 2, 60);
        return base64_encode($png);
    }

    /**
     * Wie barcodeAlsBase64(), aber hochkant (90° gedreht) -- für den schmalen Rand
     * einer Karte (Gutschein). Dompdf kann Bilder per CSS nicht zuverlässig drehen,
     * deshalb wird das PNG selbst mit GD gedreht. Ohne GD: normaler (waagrechter) Barcode.
     */
    public function barcodeHochkantAlsBase64(string $text): string
    {
        require_once __DIR__ . '/../../../vendor/autoload.php';
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        $png       = $generator->getBarcode($text, $generator::TYPE_CODE_128, 2, 70);
        if (!function_exists('imagerotate')) {
            return base64_encode($png);
        }
        // Picqer-PNG hat transparenten Hintergrund -- beim Drehen würde der schwarz.
        // Deshalb erst auf eine weiße Truecolor-Fläche kopieren, dann drehen.
        $quelle = imagecreatefromstring($png);
        $w = imagesx($quelle);
        $h = imagesy($quelle);
        $bild  = imagecreatetruecolor($w, $h);
        $weiss = imagecolorallocate($bild, 255, 255, 255);
        imagefill($bild, 0, 0, $weiss);
        imagecopy($bild, $quelle, 0, 0, 0, 0, $w, $h);
        $hoch  = imagerotate($bild, 90, $weiss);
        ob_start();
        imagepng($hoch);
        return base64_encode(ob_get_clean());
    }
}
