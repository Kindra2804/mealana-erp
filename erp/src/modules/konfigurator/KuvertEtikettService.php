<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../dokumente/PdfGenerator.php';
require_once __DIR__ . '/../dokumente/BelegBilder.php';
require_once __DIR__ . '/../preise/PreisRepository.php';

/**
 * Kuvert-Etikett (Jacky 2026-11-12): A4 mit 2 Etiketten je 210 x 148 mm (halbe Seite) für die
 * Kuverts/Tüten, in denen die fertigen Schilder liegen.
 *
 * Links das Artikelbild (groß), rechts Name, Größe, Aufbau, Preis und die Artikelnummer als
 * Code 128 -- die Kasse findet Artikel auch über die Artikelnummer, ein EAN ist nicht nötig.
 *
 * - Fertiges Schild (konfig_fertige_artikel): Überschrift = Layout-Name, Größe = Wert der Achse
 *   "Durchmesser"/"Größe", Aufbau = alle übrigen gewählten Werte. Bild: eigenes, sonst vom Layout.
 * - Jeder andere Artikel: Überschrift = Artikelname, kein Aufbau (später Preisschilder-Modul).
 * - Preis = Standardpreis (kein Aktionspreis -- das Etikett bleibt lange auf dem Kuvert).
 * - Start "unten": das erste Etikett kommt auf die untere Hälfte (Blatt, dessen obere Hälfte schon weg ist).
 */
class KuvertEtikettService
{
    private const BILD_PIXEL = 700;   // ~90 mm Bildfläche bei ~200 dpi
    private const ACHSE_GROESSE = '/durchmesser|gr(ö|oe)(ß|ss)e|^ø/iu';

    private PDO $db;
    private PdfGenerator $pdf;

    public function __construct()
    {
        $this->db  = Database::getInstance();
        $this->pdf = new PdfGenerator();
    }

    /**
     * PDF mit den Etiketten der Artikel in der übergebenen Reihenfolge.
     * @param int[] $artikelIds
     */
    public function pdf(array $artikelIds, bool $startUnten, bool $schnittlinie): string
    {
        $etiketten = [];
        foreach (array_values(array_unique(array_filter(array_map('intval', $artikelIds)))) as $id) {
            $e = $this->etikett($id);
            if ($e) $etiketten[] = $e;
        }
        if (!$etiketten) throw new RuntimeException('Keine passenden Artikel gefunden. Vater-Artikel und Layouts haben kein Etikett -- bitte die einzelnen (fertigen) Schilder auswählen.');

        // In Hälften aufteilen: null = leere Hälfte (Start unten)
        $haelften = $startUnten ? array_merge([null], $etiketten) : $etiketten;
        if (count($haelften) % 2) $haelften[] = null;

        return $this->pdf->alsString('kuvert_etikett/standard.html.twig', [
            'seiten'       => array_chunk($haelften, 2),
            'schnittlinie' => $schnittlinie,
        ]);
    }

    /** Daten eines Etiketts, null wenn es den Artikel nicht gibt oder er ein Vater/Layout ist. */
    public function etikett(int $artikelId): ?array
    {
        $st = $this->db->prepare("SELECT id, artikelnummer, name, vaterartikel_id, ist_vater, ist_konfigurierbar FROM artikel WHERE id = ?");
        $st->execute([$artikelId]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a || $a['ist_vater'] || $a['ist_konfigurierbar']) return null;

        $st = $this->db->prepare("
            SELECT f.werte_json, l.id AS layout_id, l.name AS layout_name
            FROM konfig_fertige_artikel f JOIN artikel l ON l.id = f.layout_artikel_id
            WHERE f.artikel_id = ?");
        $st->execute([$artikelId]);
        $fertig = $st->fetch(PDO::FETCH_ASSOC);

        $titel   = $a['name'];
        $groesse = '';
        $aufbau  = [];
        if ($fertig) {
            $titel = $fertig['layout_name'];
            foreach (json_decode($fertig['werte_json'], true) ?: [] as $w) {
                if ($groesse === '' && preg_match(self::ACHSE_GROESSE, (string) $w['achse'])) {
                    $groesse = $this->groesseText((string) $w['achse'], (string) $w['wert']);
                } else {
                    $aufbau[] = ['achse' => $w['achse'], 'wert' => $w['wert']];
                }
            }
        }

        $preis = (new PreisRepository())->findStandardPreis($artikelId);
        if (!$preis && $a['vaterartikel_id']) $preis = (new PreisRepository())->findStandardPreis((int) $a['vaterartikel_id']);

        // Strichbreite fest ~0,33 mm (barcodeAlsBase64: 2 px je Strich), höchstens 76 mm --
        // kurze Nummern werden so nicht auf volle Breite aufgeblasen
        $barcode = $this->pdf->barcodeAlsBase64($a['artikelnummer']);
        $bcMasse = getimagesizefromstring(base64_decode($barcode));
        $barcodeBreite = $bcMasse ? min(76, round($bcMasse[0] / 2 * 0.33, 1)) : 76;

        return $this->mitStufe([
            'artikelnummer' => $a['artikelnummer'],
            'barcode_breite'=> $barcodeBreite,
            'titel'         => $titel,
            'groesse'       => $groesse,
            'aufbau'        => $aufbau,
            'preis'         => $preis ? (float) $preis['brutto_vk'] : null,
            'bild_base64'   => $this->bild($artikelId, (int) $a['vaterartikel_id'], $fertig ? (int) $fertig['layout_id'] : 0),
            'barcode'       => $barcode,
        ]);
    }

    /**
     * Platzbedarf der rechten Spalte schätzen (124 mm hoch, unten Preis + Strichcode) und eine
     * Dichte-Stufe wählen: 0 = normal, 1 = kompakter, 2 = eng. Viele Achsen + langer Titel liefen
     * sonst in den Preis (Jacky 2026-11-13). Maße je Stufe in mm, kalibriert am Etikett
     * „Nähzimmer mit Namen“ (3 Titelzeilen, 7 Achsen): [Zeichen/Titelzeile, mm/Titelzeile, Größe,
     * Trenner, Zeichen/Wertzeile, mm/Tabellenzeile, Preisblock].
     */
    private function mitStufe(array $e): array
    {
        $stufen = [
            [16, 9.3, 14.0, 4.0, 23, 6.5, 36.0],
            [19, 7.9, 12.0, 3.5, 27, 5.5, 31.0],
            [23, 6.6, 10.0, 3.0, 32, 4.6, 27.0],
        ];
        foreach ($stufen as $i => [$zt, $hTitel, $hGroesse, $hTrenner, $zw, $hZeile, $hUnten]) {
            $h = $this->zeilen($e['titel'], $zt) * $hTitel + ($e['groesse'] !== '' ? $hGroesse : 0) + $hUnten;
            if ($e['aufbau']) {
                $h += $hTrenner;
                foreach ($e['aufbau'] as $z) $h += $this->zeilen((string) $z['wert'], $zw) * $hZeile;
            }
            if ($h <= 120 || $i === 2) return $e + ['stufe' => $i]; // 4 mm Luft auf 124 mm
        }
        return $e + ['stufe' => 2];
    }

    /** Zeilen eines Texts bei höchstens $breite Zeichen je Zeile (Umbruch an Wortgrenzen). */
    private function zeilen(string $text, int $breite): int
    {
        return max(1, substr_count(wordwrap($text, $breite, "\n", true), "\n") + 1);
    }

    /** "Durchmesser" + "18 cm" -> "Ø 18 cm"; sonst nur der Wert. */
    private function groesseText(string $achse, string $wert): string
    {
        if (preg_match('/durchmesser/iu', $achse) && mb_strpos($wert, 'Ø') === false) return 'Ø ' . $wert;
        return $wert;
    }

    /** Erstes Bild: Artikel, sonst Vater, sonst Layout. '' = kein Bild. */
    private function bild(int $artikelId, int $vaterId, int $layoutId): string
    {
        foreach (array_filter([$artikelId, $vaterId, $layoutId]) as $id) {
            $st = $this->db->prepare("SELECT dateiname FROM artikel_bilder WHERE artikel_id = ? ORDER BY position, id LIMIT 1");
            $st->execute([$id]);
            $datei = $st->fetchColumn();
            if ($datei) {
                $b = BelegBilder::vorschau($id, $datei, self::BILD_PIXEL);
                if ($b) return $b;
            }
        }
        return '';
    }
}
