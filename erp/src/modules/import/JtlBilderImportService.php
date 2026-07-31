<?php
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../artikel/ArtikelRepository.php';
require_once __DIR__ . '/../artikel/BilderRepository.php';
require_once __DIR__ . '/../artikel/BildVerarbeitung.php';
require_once __DIR__ . '/JtlCsvReader.php';

/**
 * JtlBilderImportService – ordnet Bilder aus einem JTL-Wawi-Bildexport bestehenden
 * Artikeln zu (Artikelnummer-Match), verkleinert/speichert sie über dieselbe GD-Pipeline
 * wie der manuelle Einzel-Upload (BildVerarbeitung) und legt artikel_bilder-Zeilen an.
 *
 * Eigenständiges, wiederholbares Tool — nicht an den Vater+Kind-Import gekoppelt, da
 * Jacky Bilder-Exporte unabhängig davon und wiederholt erzeugt. Artikel, die bereits
 * Bilder haben, werden übersprungen statt erneut befüllt (sicher gegen Doppel-Import).
 *
 * Ablauf: parseBilderCsv() -> baueVorschau() (kein DB-/Datei-Write) -> fuehreImportDurch().
 */
class JtlBilderImportService
{
    private ArtikelRepository $artikelRepo;
    private BilderRepository $bilderRepo;

    public function __construct()
    {
        $this->artikelRepo = new ArtikelRepository();
        $this->bilderRepo  = new BilderRepository();
    }

    /** Bilder-CSV als Artikelnummer => ['artikelname'=>, 'bilder'=>[dateiname, ...]]. */
    public function parseBilderCsv(string $csvPfad): array
    {
        $proArtikel = [];
        foreach (JtlCsvReader::lese($csvPfad) as $row) {
            $nr = $row['Artikelnummer'] ?? '';
            if ($nr === '') continue;

            $bilder = [];
            for ($i = 1; $i <= 5; $i++) {
                $dateiname = trim($row["Bild $i"] ?? '');
                if ($dateiname !== '') {
                    $bilder[] = $dateiname;
                }
            }
            if (empty($bilder)) continue;

            $proArtikel[$nr] = [
                'artikelname' => $row['Artikelname'] ?? '',
                'bilder'      => $bilder,
            ];
        }
        return $proArtikel;
    }

    /**
     * Baut die Kontroll-/Statusliste (kein DB- oder Datei-Write). Prüft pro Artikelnummer
     * den DB-Treffer + "hat bereits Bilder", pro Bilddatei Existenz im Ordner + erlaubtes
     * Format (jpg/png/webp — kein gif, siehe BildVerarbeitung::ERLAUBTE_MIMES).
     */
    public function baueVorschau(array $rows, string $ordnerPfad): array
    {
        $ergebnis = [];

        foreach ($rows as $artikelnummer => $eintrag) {
            $artikel = $this->artikelRepo->findByArtikelnummer($artikelnummer);

            $zeile = [
                'artikelnummer' => $artikelnummer,
                'artikelname'   => $eintrag['artikelname'],
                'artikel_id'    => $artikel ? (int) $artikel['id'] : null,
                'bilder'        => [],
                'status'        => 'ok',
            ];

            if (!$artikel) {
                $zeile['status'] = 'artikel_nicht_gefunden';
            } elseif (!empty($this->bilderRepo->findByArtikelId((int) $artikel['id']))) {
                $zeile['status'] = 'bereits_bilder';
            }

            foreach ($eintrag['bilder'] as $dateiname) {
                $vollpfad = rtrim($ordnerPfad, '\\/') . DIRECTORY_SEPARATOR . $dateiname;
                $bildStatus = 'ok';
                if (!is_file($vollpfad)) {
                    $bildStatus = 'datei_fehlt';
                } elseif (!$this->istErlaubtesFormat($dateiname)) {
                    $bildStatus = 'format_nicht_unterstuetzt';
                }
                $zeile['bilder'][] = ['dateiname' => $dateiname, 'status' => $bildStatus];
            }

            $ergebnis[] = $zeile;
        }

        return $ergebnis;
    }

    private function istErlaubtesFormat(string $dateiname): bool
    {
        $endung = strtolower(pathinfo($dateiname, PATHINFO_EXTENSION));
        return in_array($endung, ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    private function mimeFuerEndung(string $endung): string
    {
        return match (strtolower($endung)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            default       => '',
        };
    }

    /**
     * Führt den Import durch: pro Zeile mit status='ok', pro Bild mit status='ok' wird die
     * Datei aus $ordnerPfad kopiert+verkleinert nach uploads/artikel/{id}/ (BildVerarbeitung,
     * gleiche Pipeline wie der manuelle Upload) und ein artikel_bilder-Eintrag angelegt.
     * Quelldateien im Import-Ordner bleiben unverändert (Copy, kein Move).
     */
    public function fuehreImportDurch(array $vorschauZeilen, string $ordnerPfad): array
    {
        $ergebnisse = [];

        foreach ($vorschauZeilen as $zeile) {
            if ($zeile['status'] !== 'ok') {
                $ergebnisse[] = [
                    'artikelnummer' => $zeile['artikelnummer'], 'artikelname' => $zeile['artikelname'],
                    'erfolg' => false, 'fehler' => [$this->statusText($zeile['status'])], 'bilder' => [],
                ];
                continue;
            }

            $artikelId    = $zeile['artikel_id'];
            $uploadDir    = __DIR__ . '/../../../public/uploads/artikel/' . $artikelId . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $bildErgebnisse = [];
            $importiert     = 0;

            foreach ($zeile['bilder'] as $bild) {
                if ($bild['status'] !== 'ok') {
                    $bildErgebnisse[] = ['dateiname' => $bild['dateiname'], 'erfolg' => false, 'fehler' => $this->statusText($bild['status'])];
                    continue;
                }

                $quellpfad = rtrim($ordnerPfad, '\\/') . DIRECTORY_SEPARATOR . $bild['dateiname'];
                $endung    = strtolower(pathinfo($bild['dateiname'], PATHINFO_EXTENSION));
                $mimeTyp   = $this->mimeFuerEndung($endung);

                $zielname = 'bild_' . time() . '_' . random_int(1000, 9999) . '.jpg';
                $zielpfad = $uploadDir . $zielname;

                $ok = BildVerarbeitung::verkleinereUndSpeichere($quellpfad, $mimeTyp, $zielpfad);
                if (!$ok) {
                    $bildErgebnisse[] = ['dateiname' => $bild['dateiname'], 'erfolg' => false, 'fehler' => 'Verarbeitung fehlgeschlagen'];
                    continue;
                }

                // PNG wird von BildVerarbeitung ggf. mit .png statt .jpg gespeichert (Transparenz-Erhalt)
                $tatsaechlicherDateiname = $mimeTyp === 'image/png' ? preg_replace('/\.jpg$/', '.png', $zielname) : $zielname;

                $this->bilderRepo->insert($artikelId, $tatsaechlicherDateiname);
                $importiert++;
                $bildErgebnisse[] = ['dateiname' => $bild['dateiname'], 'erfolg' => true];
            }

            Logger::log('jtl_bilder_import.artikel', 'artikel', $artikelId, [
                'artikelnummer' => $zeile['artikelnummer'], 'bilder_importiert' => $importiert,
            ]);

            $ergebnisse[] = [
                'artikelnummer' => $zeile['artikelnummer'], 'artikelname' => $zeile['artikelname'],
                'erfolg' => $importiert > 0, 'fehler' => [], 'bilder' => $bildErgebnisse,
            ];
        }

        return $ergebnisse;
    }

    private function statusText(string $status): string
    {
        return match ($status) {
            'artikel_nicht_gefunden'    => 'Artikelnummer nicht in der DB gefunden',
            'bereits_bilder'            => 'Artikel hat bereits Bilder — übersprungen',
            'datei_fehlt'               => 'Bilddatei im Ordner nicht gefunden',
            'format_nicht_unterstuetzt' => 'Dateiformat nicht unterstützt (nur jpg/png/webp)',
            default                     => $status,
        };
    }
}
