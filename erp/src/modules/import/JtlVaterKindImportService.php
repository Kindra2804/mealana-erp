<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../artikel/ArtikelService.php';
require_once __DIR__ . '/../artikel/ArtikelRepository.php';
require_once __DIR__ . '/../hersteller/HerstellerRepository.php';
require_once __DIR__ . '/../achsen/AchsenRepository.php';
require_once __DIR__ . '/../varianten/VariantenService.php';
require_once __DIR__ . '/JtlCsvReader.php';

/**
 * JtlVaterKindImportService – CSV-Import für JTL-Vater+Kind-Artikel mit Achsenerkennung
 *
 * Erwartet zwei JTL-Exporte:
 *   1. "mitKindern"-CSV: Stammdaten für Vater- UND Kind-Zeilen (Flag "Ist Vaterartikel"),
 *      Kind-Zeile trägt in "Identifizierungsspalte Vaterartikel" die Vater-Artikelnummer.
 *   2. "Variationskombinationen"-CSV: je Kombi-Zeile Vater-Artikelnummer + Kind-Artikelnummer
 *      + bis zu 6 Achsen als "Variationsname N"/"Variationswertname N"-Paare — die Achsen
 *      liegen hier bereits strukturiert vor, kein Freitext-Parsing nötig.
 *
 * Ablauf: parseMitKindern() + parseVariationskombinationen() -> baueVorschau() (Kontrollliste,
 * kein DB-Write) -> fuehreImportDurch() (der eigentliche Import, siehe Plan
 * C:\Users\indy1\.claude\plans\sequential-hatching-papert.md).
 *
 * Bewusste Abweichung vom manuellen VarKombi-Generator: dort werden Kind-Preise über
 * Achsen-Aufpreise (artikel_achsen.preis_modus/preis_wert) berechnet. Der JTL-Export liefert
 * aber bereits den exakten Endpreis/Gewicht/Maße/UVP pro Kind, deshalb wird
 * VariantenService::erstelleKombinationen() nur für Anlage + Achsenwert-Verknüpfung genutzt und
 * direkt danach pro Kind mit den echten JTL-Werten überschrieben.
 */
class JtlVaterKindImportService
{
    private PDO $db;
    private ArtikelService $artikelService;
    private ArtikelRepository $artikelRepo;
    private HerstellerRepository $herstellerRepo;
    private AchsenRepository $achsenRepo;
    private VariantenService $variantenService;

    public function __construct()
    {
        $this->db               = Database::getInstance();
        $this->artikelService   = new ArtikelService();
        $this->artikelRepo      = new ArtikelRepository();
        $this->herstellerRepo   = new HerstellerRepository();
        $this->achsenRepo       = new AchsenRepository();
        $this->variantenService = new VariantenService();
    }

    private function komma(?string $wert): ?float
    {
        $wert = trim((string) $wert);
        if ($wert === '') return null;
        return (float) str_replace(',', '.', $wert);
    }

    /** Stammdaten-CSV (Vater UND Kind-Zeilen) als Artikelnummer => Rohzeile für schnellen Join. */
    public function parseMitKindern(string $pfad): array
    {
        $byNr = [];
        foreach (JtlCsvReader::lese($pfad) as $row) {
            $nr = $row['Artikelnummer'] ?? '';
            if ($nr === '') continue;
            $byNr[$nr] = $row;
        }
        return $byNr;
    }

    /**
     * Normalisiert einen rohen Achsenwert der Farbe-Achse auf die etablierte
     * DROPS-Konvention "Nummer Name" (z.B. "18 braun"). Die JTL/Barbara-Rohdaten
     * schreiben Nummer und [Uni]/[Mix]-Kennzeichnung uneinheitlich:
     *   - "braun [Uni] (18)"      -- Name, Tag, Nummer in Klammern am Ende
     *   - "[Uni] helloliv (115)"  -- Tag schon vorne, Nummer trotzdem in Klammern hinten
     *   - "UNI 01 natur"          -- Tag als Wort (nicht geklammert), Nummer schon vorne
     *   - "101 - weiß"            -- Nummer schon vorne, kein Tag, Bindestrich statt Leerzeichen
     *
     * [Uni]/[Mix] sind in diesem System KEINE Text-Bestandteile des Werts, sondern eigene
     * Achsen (siehe varianten_achsen id 8/9, abhängig von der Gruppenachse "Farbe", id 7)
     * -- ein erkanntes Tag steuert daher, auf welche Achse der Wert gebucht wird
     * (Rückgabe 'achse_name'), nicht den Wert-Text selbst.
     *
     * Barbaras Auslauf-Markierung "#" (Position uneinheitlich: in der Nummer, direkt
     * danach, oder in der Kind-Artikelnummer) wird erkannt und separat als 'ist_auslauf'
     * zurückgegeben -- wird auf artikel.ist_auslaufartikel gemappt (siehe fuehreImportDurch()).
     *
     * Andere Achsen (z.B. "Größe" bei Nadeln/Socken) werden NICHT angefasst -- die
     * Nummer-Name-Konvention gilt nur für Farbwerte, alles andere bleibt Rohtext.
     */
    private function normalisiereFarbwert(string $achsenName, string $wertRoh): array
    {
        if ($achsenName !== 'Farbe') {
            return ['achse_name' => $achsenName, 'wert' => $wertRoh, 'ist_auslauf' => false];
        }

        $istAuslauf = str_contains($wertRoh, '#');
        $w = str_replace('#', '', $wertRoh);

        // Geklammerter Tag -- BELIEBIGER Inhalt zählt (Barbara setzt jeden Sonderfall in
        // eckige Klammern, siehe [Uni]/[Mix]/[Print]/[Long Print]/[Recycled Denim] u.v.m.).
        // Bereits bekannte Sub-Achsen (siehe ladeFarbAchsenTags()) werden mit ihrem exakten
        // bestehenden Namen wiederverwendet, ein bisher unbekannter Tag wird 1:1 als neuer
        // Achsenname übernommen (Achse wird bei Bedarf in findeOderErstelleAchse() angelegt) --
        // deckt automatisch auch künftig neu erfundene Tags ab, ohne Codeänderung.
        $bekannteTags = $this->ladeFarbAchsenTags();
        $achseNeu     = $achsenName;

        if (preg_match('/\[([^\]]+)\]/', $w, $m)) {
            $tagRoh   = trim($m[1]);
            $achseNeu = $bekannteTags[mb_strtolower($tagRoh)] ?? ('[' . mb_convert_case($tagRoh, MB_CASE_TITLE) . ']');
            $w = str_replace($m[0], '', $w);
        } elseif (!empty($bekannteTags)) {
            // Kein Klammer-Tag -- gegen bereits bekannte Sub-Achsen als bloßes Wort prüfen
            // (z.B. "UNI 01 natur"). Bewusst NUR gegen bekannte Namen, nicht gegen beliebige
            // Wörter, damit ein echter Farbname nie versehentlich als Tag missverstanden wird.
            foreach ($bekannteTags as $tagLower => $tagName) {
                if (preg_match('/\b' . preg_quote($tagLower, '/') . '\b/i', $w)) {
                    $achseNeu = $tagName;
                    $w = preg_replace('/\b' . preg_quote($tagLower, '/') . '\b/i', '', $w);
                    break;
                }
            }
        }

        $nr = null;
        if (preg_match('/\((\d+)\)/', $w, $m)) {
            $nr = $m[1];
            $w = str_replace($m[0], '', $w);
        } elseif (preg_match('/^\s*(\d+)\s*-?\s*/', $w, $m)) {
            $nr = $m[1];
            $w = substr($w, strlen($m[0]));
        } elseif (preg_match('/\s+(\d+)\s*$/', $w, $m)) {
            // Nummer am Ende OHNE Klammern (z.B. "Weiß 1", "Creme 2" -- BorgoDePazzi Giza)
            $nr = $m[1];
            $w = substr($w, 0, -strlen($m[0]));
        }

        $name = trim(preg_replace('/\s+/', ' ', $w));
        $name = trim($name, " -,");
        // Barbaras Rohdaten schreiben Farbnamen uneinheitlich groß/klein (z.B. "Weiß" vs
        // "weiß") -- einheitlich klein, damit die Achsenwerte-Liste nicht wild gemischt aussieht.
        $name = mb_strtolower($name);

        $wertNeu = $nr !== null ? trim($nr . ' ' . $name) : $name;

        return ['achse_name' => $achseNeu, 'wert' => $wertNeu, 'ist_auslauf' => $istAuslauf];
    }

    private ?array $farbAchsenTagsCache = null;

    /**
     * Bereits bestehende Sub-Achsen der Farbe-Achse (z.B. "[Uni]", "[Mix]") als
     * Lowercase-Tag-ohne-Klammern => exakter Achsenname-Lookup. Wird für die
     * bare-Wort-Erkennung (kein Klammer-Tag im Rohtext) gebraucht UND um bei
     * geklammerten Tags den bestehenden Achsennamen exakt wiederzuverwenden statt
     * versehentlich eine zweite, leicht anders geschriebene Achse anzulegen.
     * Pro Import-Lauf einmal geladen (gecacht), da für jeden der oft tausenden
     * Werte aufgerufen.
     */
    private function ladeFarbAchsenTags(): array
    {
        if ($this->farbAchsenTagsCache !== null) {
            return $this->farbAchsenTagsCache;
        }
        $tags = [];
        $farbeAchse = $this->achsenRepo->findByCode('farbe');
        if ($farbeAchse) {
            foreach ($this->achsenRepo->findByParentId((int) $farbeAchse['id']) as $sub) {
                $tags[mb_strtolower(trim($sub['name'], '[] '))] = $sub['name'];
            }
        }
        return $this->farbAchsenTagsCache = $tags;
    }

    /** Variationskombinationen-CSV gruppiert nach Vater-Artikelnummer, je Kind die erkannten Achsen. */
    public function parseVariationskombinationen(string $pfad): array
    {
        $proVater = [];
        foreach (JtlCsvReader::lese($pfad) as $row) {
            $vaterNr   = $row['Artikelnummer'] ?? '';
            $kindNrRoh = $row['Kind Artikelnummer'] ?? '';
            if ($vaterNr === '' || $kindNrRoh === '') continue;

            $kindNameRoh = $row['Kind Artikelname'] ?? '';
            $istAuslauf  = str_contains($kindNrRoh, '#') || str_contains($kindNameRoh, '#');
            $kindNr      = str_replace('#', '', $kindNrRoh);

            $achsen = [];
            for ($i = 1; $i <= 6; $i++) {
                $achsenName    = rtrim(trim($row["Variationsname $i"] ?? ''), ':');
                $achsenWertRoh = trim($row["Variationswertname $i"] ?? '');
                if ($achsenName === '' || $achsenWertRoh === '') continue;

                $normalisiert = $this->normalisiereFarbwert($achsenName, $achsenWertRoh);
                if ($normalisiert['ist_auslauf']) $istAuslauf = true;

                $achsen[] = ['name' => $normalisiert['achse_name'], 'wert' => $normalisiert['wert']];
            }

            $proVater[$vaterNr][] = [
                'kind_artikelnummer' => $kindNr,
                'kind_name_roh'      => $kindNameRoh, // Fallback falls keine Achse erkannt wurde
                'kind_netto'         => $this->komma($row['Kind VK Netto'] ?? ''),
                'kind_brutto'        => $this->komma($row['Kind VK Brutto'] ?? ''),
                'achsen'             => $achsen,
                'ist_auslauf'        => $istAuslauf,
            ];
        }
        return $proVater;
    }

    private function ladeLookups(): array
    {
        $steuerMap = [];
        foreach ($this->db->query("SELECT id, satz FROM steuerklassen WHERE aktiv = 1")->fetchAll() as $s) {
            $steuerMap[(string)(int)$s['satz']] = ['id' => (int)$s['id'], 'satz' => (float)$s['satz']];
        }

        $einheitMap = [];
        foreach ($this->db->query("SELECT id, kuerzel FROM einheiten")->fetchAll() as $e) {
            $einheitMap[mb_strtolower(trim($e['kuerzel']))] = (int)$e['id'];
        }

        $herstellerMap = [];
        foreach ($this->herstellerRepo->findAll() as $h) {
            $herstellerMap[mb_strtolower(trim($h['name']))] = (int)$h['id'];
        }

        return ['steuer' => $steuerMap, 'einheit' => $einheitMap, 'hersteller' => $herstellerMap];
    }

    /**
     * Kürzt den DROPS-Markennamen am Anfang des Artikelnamens weg (Barbaras Wunsch:
     * "DROPS " macht Vater- UND Kind-Namen unnötig lang) -- AUSSER bei der
     * "DROPS loves You"-Serie, dort ist der Markenname Teil des eigentlichen
     * Produktnamens und bleibt bewusst stehen. Groß-/Kleinschreibung im Rohtext ist
     * uneinheitlich ("DROPS loves You" vs. "DROPS Loves You"), daher case-insensitive.
     */
    private function kuerzeDropsName(string $name): string
    {
        if (preg_match('/^DROPS\s+loves\s+you/i', $name)) {
            return $name;
        }
        return preg_replace('/^DROPS\s+/i', '', $name);
    }

    /**
     * Baut die Vorschau-/Kontrollliste-Struktur (kein DB-Write). Verknüpft Kombi-Zeilen
     * (Achsen) mit den Stammdaten-Zeilen für Vater und Kinder über die Artikelnummer.
     */
    public function baueVorschau(array $mitKindern, array $kombisProVater): array
    {
        $lookups = $this->ladeLookups();

        $vaeter             = [];
        $einheitenUnbekannt = [];

        foreach ($kombisProVater as $vaterNr => $kombis) {
            $vaterRow = $mitKindern[$vaterNr] ?? null;
            if (!$vaterRow || ($vaterRow['Ist Vaterartikel'] ?? '0') !== '1') {
                continue; // Vater-Stammdatenzeile fehlt oder Flag nicht gesetzt
            }

            $herstellerName    = mb_strtolower(trim($vaterRow['Hersteller'] ?? ''));
            $herstellerTreffer = $herstellerName !== '' && isset($lookups['hersteller'][$herstellerName]);

            $verkaufseinheitRoh = trim($vaterRow['Verkaufseinheit'] ?? '');
            if (!isset($lookups['einheit'][mb_strtolower($verkaufseinheitRoh)])) {
                $einheitenUnbekannt[$verkaufseinheitRoh] = true;
            }

            $kinder         = [];
            $achsenDistinkt = [];

            foreach ($kombis as $kombi) {
                $kindRow = $mitKindern[$kombi['kind_artikelnummer']] ?? null;

                foreach ($kombi['achsen'] as $a) {
                    $achsenDistinkt[$a['name'] . '|' . $a['wert']] = $a;
                }

                $kindVerkaufseinheitRoh = $kindRow ? trim($kindRow['Verkaufseinheit'] ?? '') : '';
                if (!isset($lookups['einheit'][mb_strtolower($kindVerkaufseinheitRoh)])) {
                    $einheitenUnbekannt[$kindVerkaufseinheitRoh] = true;
                }

                // Kind-Name aus Vatername + Achse(n) + Wert neu zusammensetzen statt den
                // rohen (oft uneinheitlich formatierten) CSV-Namen zu übernehmen -- gleiche
                // Konvention wie der manuelle VarKombi-Generator: die Achse "Farbe" selbst
                // wird NICHT in den Namen geschrieben (redundant), [Uni]/[Mix] hingegen schon
                // (siehe normalisiereFarbwert()). Ohne erkannte Achsen (z.B. Stammdatenzeile
                // fehlt) bleibt der rohe CSV-Name als Fallback.
                $vaterNameTrim = $this->kuerzeDropsName(trim($vaterRow['Artikelname'] ?? ''));
                $kindNameNeu   = $kombi['kind_name_roh'];
                if (!empty($kombi['achsen'])) {
                    $teile = array_map(
                        fn($a) => trim(($a['name'] !== 'Farbe' ? $a['name'] . ' ' : '') . $a['wert']),
                        $kombi['achsen']
                    );
                    $kindNameNeu = trim($vaterNameTrim . ' ' . implode(' ', $teile));
                }

                $kinder[] = [
                    'artikelnummer'       => $kombi['kind_artikelnummer'],
                    'name'                => $kindNameNeu,
                    'achsen'              => $kombi['achsen'],
                    'brutto_vk'           => $kombi['kind_brutto'],
                    'netto_vk'            => $kombi['kind_netto'],
                    'ean'                 => $kindRow['GTIN'] ?? '',
                    'gewicht_artikel'     => $kindRow ? $this->komma($kindRow['Artikelgewicht'] ?? '') : null,
                    'breite'              => $kindRow ? $this->komma($kindRow['Breite'] ?? '') : null,
                    'hoehe'               => $kindRow ? $this->komma($kindRow['Höhe'] ?? '') : null,
                    'laenge'              => $kindRow ? $this->komma($kindRow['Länge'] ?? '') : null,
                    'uvp'                 => $kindRow ? $this->komma($kindRow['UVP'] ?? '') : null,
                    'verkaufseinheit_roh' => $kindVerkaufseinheitRoh,
                    'stammdaten_fehlen'   => $kindRow === null,
                    'ist_auslauf'         => $kombi['ist_auslauf'],
                    'bereits_vorhanden'   => $this->artikelRepo->findByArtikelnummer($kombi['kind_artikelnummer']) !== false,
                ];
            }

            $vaeter[] = [
                'artikelnummer'          => $vaterNr,
                'name'                   => $this->kuerzeDropsName(trim($vaterRow['Artikelname'] ?? '')),
                'kurzbeschreibung'       => $vaterRow['Kurzbeschreibung'] ?? '',
                'beschreibung'           => $vaterRow['Beschreibung'] ?? '',
                'brutto_vk'              => $this->komma($vaterRow['Brutto-VK'] ?? ''),
                'netto_vk'               => $this->komma($vaterRow['Netto-VK'] ?? ''),
                'uvp'                    => $this->komma($vaterRow['UVP'] ?? ''),
                'steuersatz'             => $vaterRow['Steuersatz in %'] ?? '',
                'charge_pflicht'         => ($vaterRow['Artikel mit Charge'] ?? '0') === '1' ? 1 : 0,
                'gewicht_artikel'        => $this->komma($vaterRow['Artikelgewicht'] ?? ''),
                'breite'                 => $this->komma($vaterRow['Breite'] ?? ''),
                'hoehe'                  => $this->komma($vaterRow['Höhe'] ?? ''),
                'laenge'                 => $this->komma($vaterRow['Länge'] ?? ''),
                'verkaufseinheit_roh'    => $verkaufseinheitRoh,
                'inhalt_menge'           => $this->komma($vaterRow['Inhalt/Menge'] ?? ''),
                'grundpreis_anzeigen'    => ($vaterRow['Grundpreis ausweisen'] ?? '0') === '1' ? 1 : 0,
                'grundpreis_bezugsmenge' => $this->komma($vaterRow['GP-Bezugsmenge'] ?? ''),
                'inhalt_einheit'         => trim($vaterRow['Einheit Bezugsmenge'] ?? '') !== '' ? trim($vaterRow['Einheit Bezugsmenge']) : null,
                'hersteller_name'        => $vaterRow['Hersteller'] ?? '',
                'hersteller_treffer'     => $herstellerTreffer,
                'kinder'                 => $kinder,
                'achsen'                 => array_values($achsenDistinkt),
                'bereits_vorhanden'      => $this->artikelRepo->findByArtikelnummer($vaterNr) !== false,
            ];
        }

        [$normale, $verwaisteKinderAnzahl] = $this->baueVorschauNormaleArtikel($mitKindern, $kombisProVater, $lookups, $einheitenUnbekannt);

        // Vater-Zeilen (Flag "Ist Vaterartikel"), für die keine Kombinationsdaten vorliegen --
        // z.B. weil gar keine Variationskombinationen-CSV hochgeladen wurde (die Datei ist
        // optional, manche Kategorien enthalten NUR normale Artikel). Werden bewusst nicht als
        // normaler Artikel behandelt (sie sind ja fachlich ein Vater, nur ohne bekannte Kinder),
        // sondern in der Vorschau als Warnung ausgewiesen und übersprungen.
        $vaterOhneKombidatei = 0;
        foreach ($mitKindern as $nr => $row) {
            if (($row['Ist Vaterartikel'] ?? '0') === '1' && !isset($kombisProVater[$nr])) {
                $vaterOhneKombidatei++;
            }
        }

        return [
            'vaeter'                       => $vaeter,
            'normale'                      => $normale,
            'einheiten_unbekannt'          => array_keys($einheitenUnbekannt),
            'verwaiste_kinder_anzahl'      => $verwaisteKinderAnzahl,
            'vater_ohne_kombidatei_anzahl' => $vaterOhneKombidatei,
        ];
    }

    /**
     * "Normale" Artikel: Zeilen aus der Stammdaten-CSV, die weder Vater (Flag "Ist Vaterartikel")
     * noch Kind eines Vaters sind (Spalte "Identifizierungsspalte Vaterartikel" leer). Kommen in
     * gemischten Kategorie-Exporten vor (z.B. "Sale" oder "Amigurumi"), wo Variationskombi-Artikel
     * UND einfache Einzelartikel gemeinsam in einer Kategorie stehen. $einheitenUnbekannt wird per
     * Referenz mitbefüllt (gleiche Sammelliste wie bei Väter/Kinder).
     *
     * Kind-Zeilen, deren Vater-Kennung gesetzt ist, für die aber KEINE Kombinationszeile existiert
     * (Achsen unbekannt, JTL-Exportlücke), werden bewusst NICHT als normale Artikel behandelt --
     * sie würden sonst fälschlich als eigenständiges Produkt statt als Variante angelegt. Sie
     * werden gezählt und der Vorschau als Warnung mitgegeben, aber übersprungen.
     */
    private function baueVorschauNormaleArtikel(array $mitKindern, array $kombisProVater, array $lookups, array &$einheitenUnbekannt): array
    {
        $kindNummern = [];
        foreach ($kombisProVater as $kombis) {
            foreach ($kombis as $kombi) {
                $kindNummern[$kombi['kind_artikelnummer']] = true;
            }
        }

        $normale = [];
        $verwaisteKinderAnzahl = 0;

        foreach ($mitKindern as $nr => $row) {
            if (($row['Ist Vaterartikel'] ?? '0') === '1') continue;
            if (isset($kindNummern[$nr])) continue;

            if (trim($row['Identifizierungsspalte Vaterartikel'] ?? '') !== '') {
                $verwaisteKinderAnzahl++;
                continue;
            }

            $herstellerName    = mb_strtolower(trim($row['Hersteller'] ?? ''));
            $herstellerTreffer = $herstellerName !== '' && isset($lookups['hersteller'][$herstellerName]);

            $verkaufseinheitRoh = trim($row['Verkaufseinheit'] ?? '');
            if (!isset($lookups['einheit'][mb_strtolower($verkaufseinheitRoh)])) {
                $einheitenUnbekannt[$verkaufseinheitRoh] = true;
            }

            $normale[] = [
                'artikelnummer'          => $nr,
                'name'                   => $this->kuerzeDropsName(trim($row['Artikelname'] ?? '')),
                'kurzbeschreibung'       => $row['Kurzbeschreibung'] ?? '',
                'beschreibung'           => $row['Beschreibung'] ?? '',
                'brutto_vk'              => $this->komma($row['Brutto-VK'] ?? ''),
                'netto_vk'               => $this->komma($row['Netto-VK'] ?? ''),
                'uvp'                    => $this->komma($row['UVP'] ?? ''),
                'steuersatz'             => $row['Steuersatz in %'] ?? '',
                'charge_pflicht'         => ($row['Artikel mit Charge'] ?? '0') === '1' ? 1 : 0,
                'gewicht_artikel'        => $this->komma($row['Artikelgewicht'] ?? ''),
                'breite'                 => $this->komma($row['Breite'] ?? ''),
                'hoehe'                  => $this->komma($row['Höhe'] ?? ''),
                'laenge'                 => $this->komma($row['Länge'] ?? ''),
                'verkaufseinheit_roh'    => $verkaufseinheitRoh,
                'inhalt_menge'           => $this->komma($row['Inhalt/Menge'] ?? ''),
                'grundpreis_anzeigen'    => ($row['Grundpreis ausweisen'] ?? '0') === '1' ? 1 : 0,
                'grundpreis_bezugsmenge' => $this->komma($row['GP-Bezugsmenge'] ?? ''),
                'inhalt_einheit'         => trim($row['Einheit Bezugsmenge'] ?? '') !== '' ? trim($row['Einheit Bezugsmenge']) : null,
                'hersteller_name'        => $row['Hersteller'] ?? '',
                'hersteller_treffer'     => $herstellerTreffer,
                'ean'                    => $row['GTIN'] ?? '',
                'bereits_vorhanden'      => $this->artikelRepo->findByArtikelnummer($nr) !== false,
            ];
        }

        return [$normale, $verwaisteKinderAnzahl];
    }

    private function loeseEinheitAuf(string $roh, array $einheitMap, array $userMapping): ?int
    {
        // Kein früher return bei leerem $roh -- eine leere Verkaufseinheit ist einer der
        // Werte, die der User im Mapping-Formular auf "(leer)" explizit zuordnen kann
        // (siehe jtl_import.php), ein früher Abbruch hier würde diese Auswahl ignorieren.
        $key = mb_strtolower($roh);
        if (isset($einheitMap[$key]) && $roh !== '') return $einheitMap[$key];
        return isset($userMapping[$roh]) && $userMapping[$roh] !== '' ? (int) $userMapping[$roh] : null;
    }

    /**
     * Weist eine Kategorie ZUSÄTZLICH zu bestehenden Kategorien zu, statt sie zu ersetzen.
     * ArtikelService::saveKategorien() erwartet die komplette Ziel-Kategorienliste und LÖSCHT
     * alles was nicht mehr enthalten ist (korrektes Verhalten für den manuellen Kategorie-Editor,
     * wo der User immer die volle Liste absendet) -- für diesen Import ist das aber gefährlich:
     * viele Artikel hier sind bereits vorhanden und stehen schon in einer ANDEREN Kategorie
     * (z.B. "Ferner Wolle"), dieser Import soll eine WEITERE Kategorie ergänzen (z.B. "Sale"
     * oder "Amigurumi"), nicht die bestehende ersetzen. Kinder werden durch saveKategorien()
     * automatisch mit der vollen (jetzt korrekt zusammengeführten) Liste des Vaters synchronisiert.
     */
    private function fuegeKategorieHinzu(int $artikelId, int $kategorieId): void
    {
        $bestehendeIds = array_column($this->artikelService->getKategorienFuerArtikel($artikelId), 'id');
        $neueIds       = array_values(array_unique(array_merge($bestehendeIds, [$kategorieId])));
        $this->artikelService->saveKategorien($artikelId, $neueIds);
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $text = preg_replace('/[^a-z0-9]+/', '_', $text);
        return trim($text, '_');
    }

    /**
     * Findet eine globale Achse per Code oder legt sie neu an (Darstellungsform-Default:
     * dropdown). Ein noch unbekannter geklammerter Farb-Tag (z.B. erstmalig auftauchendes
     * "[Recycled Denim]", siehe normalisiereFarbwert()) wird dabei automatisch als abhängig
     * von der bestehenden Farbe-Achse angelegt -- sonst fehlt die Sub-Achsen-Union-Logik
     * (VariantenService::baueAchsenDimensionen()) im VarKombi-Generator UND im Shop-Sync
     * die Verknüpfung zur Gruppenachse.
     */
    private function findeOderErstelleAchse(string $name): int
    {
        $code      = $this->slugify($name);
        $bestehend = $this->achsenRepo->findByCode($code);
        if ($bestehend) {
            return (int) $bestehend['id'];
        }

        $abhaengigVonId = null;
        if (preg_match('/^\[.+\]$/', $name)) {
            $farbeAchse = $this->achsenRepo->findByCode('farbe');
            $abhaengigVonId = $farbeAchse ? (int) $farbeAchse['id'] : null;
        }

        return $this->achsenRepo->insert([
            'name'                   => $name,
            'code'                   => $code,
            'darstellungsform'       => 'dropdown',
            'abhaengig_von_achse_id' => $abhaengigVonId,
            'sort_order'             => 0,
        ]);
    }

    /**
     * Führt den eigentlichen Import durch. $vorschauVaeter kommt aus baueVorschau()['vaeter'].
     * $korrekturen: [ursprüngliche Vater-Artikelnummer => ['artikelnummer'=>, 'name'=>,
     *                'artikeltyp_code'=>, 'artikel_gruppe_id'=>,
     *                'kinder' => [ursprüngliche Kind-Artikelnummer => ['artikelnummer'=>, 'name'=>]]]]
     * Artikeltyp + Artikelgruppe kommen bewusst PRO VATER aus $korrekturen statt als ein
     * globaler Parameter -- gemischte Kategorien (z.B. "Sale") können Väter mit
     * unterschiedlichem Artikeltyp/Artikelgruppe enthalten (Garn + Zubehör in derselben
     * Kategorie). Nur relevant für neu angelegte Väter, bei bereits bestehenden wird nichts
     * davon angefasst (siehe unten).
     * $einheitMapping: [roher Verkaufseinheit-Text => einheiten.id] für die in der Vorschau
     *                  als unbekannt markierten Werte.
     */
    public function fuehreImportDurch(
        array $vorschauVaeter,
        int $kategorieId,
        array $einheitMapping,
        array $korrekturen
    ): array {
        $lookups    = $this->ladeLookups();
        $ergebnisse = [];

        foreach ($vorschauVaeter as $vater) {
            $korr      = $korrekturen[$vater['artikelnummer']] ?? [];
            $vaterNr   = trim($korr['artikelnummer'] ?? $vater['artikelnummer']);
            $vaterName = trim($korr['name'] ?? $vater['name']);

            // Resume-Fähigkeit: existiert die Artikelnummer schon (z.B. weil ein früherer
            // Lauf für andere Väter abgebrochen ist), wird NICHT neu angelegt, sondern der
            // bestehende Vater übernommen -- Kategorie/Achsen/Kinder darunter laufen trotzdem
            // durch (alles idempotent), damit ein erneuter Import-Durchlauf mit denselben
            // Dateien den Batch einfach zu Ende bringt statt bei jedem bereits vorhandenen
            // Vater als "Fehler" (Artikelnummer existiert bereits) aufzuschlagen.
            $vorhandenerVater = $this->artikelRepo->findByArtikelnummer($vaterNr);

            if ($vorhandenerVater) {
                $vaterId = (int) $vorhandenerVater['id'];

                // 🔴 Bug bis 2026-08-01: $einheitId/$herstellerId wurden nur im "neu anlegen"-Zweig
                // unten gesetzt, aber weiter unten in der Kinder-Anreicherung UNBEDINGT gelesen --
                // bei einem bereits bestehenden Vater (Resume-Fall) blieben sie komplett undefiniert
                // und krachten beim ersten Kind mit einem FK-Fehler auf einheit_id. Fix: bei einem
                // bestehenden Vater dessen EIGENE aktuelle Werte als Fallback für die Kinder laden.
                $vorhandenerVaterVoll = $this->artikelRepo->findById($vaterId);
                $einheitId    = $vorhandenerVaterVoll['einheit_id'] ?? null;
                $herstellerId = $vorhandenerVaterVoll['hersteller_id'] ?? null;
            } else {
                $artikeltypCode  = trim($korr['artikeltyp_code'] ?? '');
                $artikelGruppeId = (int) ($korr['artikel_gruppe_id'] ?? 0);
                if ($artikeltypCode === '' || $artikelGruppeId <= 0) {
                    $ergebnisse[] = [
                        'artikelnummer' => $vaterNr, 'name' => $vaterName, 'erfolg' => false,
                        'fehler' => ['Artikeltyp und/oder Artikelgruppe nicht ausgewählt'],
                    ];
                    continue;
                }

                $steuerSatzStr  = (string) (int) $vater['steuersatz'];
                $steuerklasseId = $lookups['steuer'][$steuerSatzStr]['id'] ?? null;
                if ($steuerklasseId === null) {
                    $ergebnisse[] = [
                        'artikelnummer' => $vaterNr, 'name' => $vaterName, 'erfolg' => false,
                        'fehler' => ["Steuersatz \"{$vater['steuersatz']}%\" nicht gefunden"],
                    ];
                    continue;
                }

                $einheitId = $this->loeseEinheitAuf($vater['verkaufseinheit_roh'], $lookups['einheit'], $einheitMapping);
                if ($einheitId === null) {
                    $ergebnisse[] = [
                        'artikelnummer' => $vaterNr, 'name' => $vaterName, 'erfolg' => false,
                        'fehler' => ["Verkaufseinheit \"{$vater['verkaufseinheit_roh']}\" konnte nicht zugeordnet werden"],
                    ];
                    continue;
                }

                $herstellerName = mb_strtolower(trim($vater['hersteller_name']));
                $herstellerId   = $lookups['hersteller'][$herstellerName] ?? null;

                $vaterData = [
                    'artikelnummer'          => $vaterNr,
                    'name'                   => $vaterName,
                    'artikeltyp'             => $artikeltypCode,
                    'steuerklasse_id'        => $steuerklasseId,
                    'artikel_gruppe_id'      => $artikelGruppeId,
                    'einheit_id'             => $einheitId,
                    'hersteller_id'          => $herstellerId,
                    'kurzbeschreibung'       => $vater['kurzbeschreibung'] ?: null,
                    'beschreibung'           => $vater['beschreibung'] ?: null,
                    'inhalt_menge'           => $vater['inhalt_menge'],
                    'inhalt_einheit'         => $vater['inhalt_einheit'],
                    'herkunftsland'          => null,
                    'gewicht_artikel'        => $vater['gewicht_artikel'],
                    'breite'                 => $vater['breite'],
                    'hoehe'                  => $vater['hoehe'],
                    'laenge'                 => $vater['laenge'],
                    'grundpreis_bezugsmenge' => $vater['grundpreis_bezugsmenge'],
                    'grundpreis_anzeigen'    => $vater['grundpreis_anzeigen'],
                    'charge_pflicht'         => $vater['charge_pflicht'],
                    'aktiv'                  => 1,
                    'brutto_vk'              => $vater['brutto_vk'],
                    'netto_vk'               => $vater['netto_vk'],
                ];

                $result = $this->artikelService->save($vaterData);
                if (!$result['erfolg']) {
                    $ergebnisse[] = ['artikelnummer' => $vaterNr, 'name' => $vaterName, 'erfolg' => false, 'fehler' => $result['fehler']];
                    continue;
                }
                $vaterId = $result['id'];

                $this->db->prepare("UPDATE artikel SET ist_vater = 1 WHERE id = :id")->execute(['id' => $vaterId]);
                if ($vater['uvp'] !== null) {
                    $this->db->prepare("UPDATE artikel SET uvp = :uvp WHERE id = :id")
                        ->execute(['uvp' => $vater['uvp'], 'id' => $vaterId]);
                }
            }

            $this->fuegeKategorieHinzu($vaterId, $kategorieId);

            // Achsen: distinct (Name, Wert)-Paare aus allen Kind-Kombis sammeln, Achse per Code finden/anlegen
            $achsenIdByName     = [];
            $werteFuerSpeichern = [];
            $seenValues         = [];
            foreach ($vater['kinder'] as $kind) {
                foreach ($kind['achsen'] as $a) {
                    if (!isset($achsenIdByName[$a['name']])) {
                        $achsenIdByName[$a['name']] = $this->findeOderErstelleAchse($a['name']);
                    }
                    $achseId = $achsenIdByName[$a['name']];
                    $valKey  = $achseId . '|' . $a['wert'];
                    if (!isset($seenValues[$valKey])) {
                        $seenValues[$valKey]  = true;
                        $werteFuerSpeichern[] = ['achse_id' => $achseId, 'wert' => $a['wert'], 'id' => 0];
                    }
                }
            }
            $achsenIds = array_values($achsenIdByName);
            if (!empty($achsenIds)) {
                $this->variantenService->speichereAchsenUndWerte($vaterId, $achsenIds, $werteFuerSpeichern);
            }

            // Wert-IDs nachladen (erst nach dem Speichern bekannt) für den Kombinationsgenerator
            $wertIdLookup = [];
            foreach ($this->variantenService->findWerteByArtikelId($vaterId) as $w) {
                $wertIdLookup[(int) $w['achse_id'] . '|' . $w['wert']] = (int) $w['id'];
            }

            $kombis         = [];
            $kindErgebnisse = [];
            foreach ($vater['kinder'] as $kind) {
                $kindKorr = $korr['kinder'][$kind['artikelnummer']] ?? [];
                $kindNr   = trim($kindKorr['artikelnummer'] ?? $kind['artikelnummer']);
                $kindName = trim($kindKorr['name'] ?? $kind['name']);

                $wertIds = [];
                foreach ($kind['achsen'] as $a) {
                    $achseId = $achsenIdByName[$a['name']] ?? null;
                    $wid     = $achseId ? ($wertIdLookup[$achseId . '|' . $a['wert']] ?? null) : null;
                    if ($wid) $wertIds[] = $wid;
                }
                if (empty($wertIds)) {
                    $kindErgebnisse[] = ['artikelnummer' => $kindNr, 'name' => $kindName, 'erfolg' => false, 'fehler' => ['Keine Achsenwerte erkannt']];
                    continue;
                }

                // War das Kind VOR diesem Lauf schon vorhanden? (z.B. Farbvariante eines
                // Garns, die schon unter einer anderen Kategorie importiert wurde). Muss
                // VOR erstelleKombinationen() geprüft werden, weil dessen interne
                // findIdByArtikelnummer()-Wiederverwendung sonst nicht mehr unterscheidbar ist.
                $kombis[] = [
                    'artikelnummer'  => $kindNr,
                    'name'           => $kindName,
                    'ean'            => $kind['ean'],
                    'key'            => implode(',', $wertIds),
                    '_orig'          => $kind,
                    '_war_vorhanden' => $this->artikelRepo->findByArtikelnummer($kindNr) !== false,
                ];
            }

            if (!empty($kombis)) {
                $vaterArray = $this->artikelService->findById($vaterId);
                $kombiResult = $this->variantenService->erstelleKombinationen($vaterArray, true, $kombis);

                // Achsen-Aufpreis-Anpassungen (passeKindPreiseAn() -- echtes UPDATE, keine
                // INSERT-IGNORE-Kopie wie die übrigen Vater-Relationen) dürfen bereits
                // bestehende Kinder nicht treffen, gleiche Begründung wie unten.
                $warVorhandenByKindId = [];
                foreach ($kombis as $idx => $kombi) {
                    if (isset($kombiResult['ids'][$idx])) {
                        $warVorhandenByKindId[$kombiResult['ids'][$idx]] = $kombi['_war_vorhanden'];
                    }
                }
                $preisAnpassungenNeu = array_filter(
                    $kombiResult['preisAnpassungen'] ?? [],
                    fn($kindId) => empty($warVorhandenByKindId[$kindId]),
                    ARRAY_FILTER_USE_KEY
                );

                $this->artikelService->kopiereVaterRelationenZuKindern($vaterId, $kombiResult['ids'], $preisAnpassungenNeu);

                // Pro Kind die echten JTL-Werte (Preis/Maße/UVP/Einheit) statt der Vater-Vererbung setzen --
                // ABER NUR bei Kindern, die in diesem Lauf wirklich neu angelegt wurden. Ein bereits
                // bestehendes Kind (z.B. Farbvariante, die schon unter einer anderen Kategorie
                // importiert war) bekommt NUR die Kategorie (oben via kopiereVaterRelationenZuKindern
                // additiv über copyKategorien) -- Preis/Gewicht/Maße/Hersteller/Auslauf-Flag bleiben
                // unangetastet. Sonst würde z.B. dieser Sale-Export (der für 196 von 465 Kindern gar
                // keine eigene Stammdatenzeile mitliefert) echte Bestandsdaten mit NULL überschreiben.
                foreach ($kombis as $idx => $kombi) {
                    $kindId = $kombiResult['ids'][$idx] ?? null;
                    if (!$kindId) continue;

                    if ($kombi['_war_vorhanden']) {
                        $kindErgebnisse[] = ['artikelnummer' => $kombi['artikelnummer'], 'name' => $kombi['name'], 'erfolg' => true, 'id' => $kindId, 'nur_kategorie' => true];
                        continue;
                    }

                    $ean = trim($kombi['ean'] ?? '');
                    if ($ean !== '') {
                        $this->artikelService->speichereCode((int) $kindId, 'GTIN13', $ean);
                    }

                    $orig = $kombi['_orig'];

                    if ($orig['brutto_vk'] !== null && $orig['netto_vk'] !== null) {
                        $this->artikelRepo->updatePreis($kindId, $orig['brutto_vk'], $orig['netto_vk']);
                    }

                    $kindEinheitId = $this->loeseEinheitAuf($orig['verkaufseinheit_roh'], $lookups['einheit'], $einheitMapping) ?? $einheitId;

                    // ist_auslaufartikel: Barbaras "#"-Markierung aus normalisiereFarbwert()
                    // (siehe parseVariationskombinationen()) -- Auslauffarbe wird beim Import
                    // direkt korrekt geflaggt statt manuell nachgetragen werden zu müssen.
                    $this->db->prepare("
                        UPDATE artikel SET
                            gewicht_artikel = :gewicht, breite = :breite, hoehe = :hoehe, laenge = :laenge,
                            uvp = :uvp, einheit_id = :einheit_id, charge_pflicht = :charge_pflicht,
                            hersteller_id = :hersteller_id, ist_auslaufartikel = :ist_auslauf
                        WHERE id = :id
                    ")->execute([
                        'gewicht'        => $orig['gewicht_artikel'],
                        'breite'         => $orig['breite'],
                        'hoehe'          => $orig['hoehe'],
                        'laenge'         => $orig['laenge'],
                        'uvp'            => $orig['uvp'],
                        'einheit_id'     => $kindEinheitId,
                        'charge_pflicht' => $vater['charge_pflicht'],
                        'hersteller_id'  => $herstellerId,
                        'ist_auslauf'    => !empty($orig['ist_auslauf']) ? 1 : 0,
                        'id'             => $kindId,
                    ]);

                    $kindErgebnisse[] = ['artikelnummer' => $kombi['artikelnummer'], 'name' => $kombi['name'], 'erfolg' => true, 'id' => $kindId];
                }
            }

            Logger::log('jtl_import.vater_anlegen', 'artikel', $vaterId, [
                'artikelnummer' => $vaterNr, 'kinder_anzahl' => count($kindErgebnisse),
            ]);

            $ergebnisse[] = [
                'artikelnummer' => $vaterNr, 'name' => $vaterName, 'erfolg' => true, 'id' => $vaterId,
                'kinder'        => $kindErgebnisse, 'nur_kategorie' => (bool) $vorhandenerVater,
            ];
        }

        return $ergebnisse;
    }

    /**
     * Führt den Import für "normale" (Standalone-)Artikel durch -- Zeilen aus der Stammdaten-CSV
     * ohne Vater/Kind-Beziehung, siehe baueVorschauNormaleArtikel(). Existiert die Artikelnummer
     * bereits (z.B. weil der Artikel schon unter einer anderen Kategorie importiert wurde),
     * wird NICHTS an den Stammdaten verändert -- nur die neue Kategorie wird ergänzt. Sonst
     * exakt dieselbe Anlage-Logik wie im "neu anlegen"-Zweig von fuehreImportDurch(), nur ohne
     * Achsen/Kinder. $korrekturen: [ursprüngliche Artikelnummer => ['artikelnummer'=>, 'name'=>,
     * 'artikeltyp_code'=>, 'artikel_gruppe_id'=>]] -- Artikeltyp/Artikelgruppe kommen bewusst
     * PRO ARTIKEL statt global, gleiche Begründung wie in fuehreImportDurch().
     */
    public function fuehreNormaleImportDurch(
        array $vorschauNormale,
        int $kategorieId,
        array $einheitMapping,
        array $korrekturen
    ): array {
        $lookups    = $this->ladeLookups();
        $ergebnisse = [];

        foreach ($vorschauNormale as $artikel) {
            $korr = $korrekturen[$artikel['artikelnummer']] ?? [];
            $nr   = trim($korr['artikelnummer'] ?? $artikel['artikelnummer']);
            $name = trim($korr['name'] ?? $artikel['name']);

            $vorhandener = $this->artikelRepo->findByArtikelnummer($nr);
            if ($vorhandener) {
                $artikelId = (int) $vorhandener['id'];
                $this->fuegeKategorieHinzu($artikelId, $kategorieId);
                $ergebnisse[] = ['artikelnummer' => $nr, 'name' => $name, 'erfolg' => true, 'id' => $artikelId, 'nur_kategorie' => true];
                continue;
            }

            $artikeltypCode  = trim($korr['artikeltyp_code'] ?? '');
            $artikelGruppeId = (int) ($korr['artikel_gruppe_id'] ?? 0);
            if ($artikeltypCode === '' || $artikelGruppeId <= 0) {
                $ergebnisse[] = [
                    'artikelnummer' => $nr, 'name' => $name, 'erfolg' => false,
                    'fehler' => ['Artikeltyp und/oder Artikelgruppe nicht ausgewählt'],
                ];
                continue;
            }

            $steuerSatzStr  = (string) (int) $artikel['steuersatz'];
            $steuerklasseId = $lookups['steuer'][$steuerSatzStr]['id'] ?? null;
            if ($steuerklasseId === null) {
                $ergebnisse[] = [
                    'artikelnummer' => $nr, 'name' => $name, 'erfolg' => false,
                    'fehler' => ["Steuersatz \"{$artikel['steuersatz']}%\" nicht gefunden"],
                ];
                continue;
            }

            $einheitId = $this->loeseEinheitAuf($artikel['verkaufseinheit_roh'], $lookups['einheit'], $einheitMapping);
            if ($einheitId === null) {
                $ergebnisse[] = [
                    'artikelnummer' => $nr, 'name' => $name, 'erfolg' => false,
                    'fehler' => ["Verkaufseinheit \"{$artikel['verkaufseinheit_roh']}\" konnte nicht zugeordnet werden"],
                ];
                continue;
            }

            $herstellerName = mb_strtolower(trim($artikel['hersteller_name']));
            $herstellerId   = $lookups['hersteller'][$herstellerName] ?? null;

            $data = [
                'artikelnummer'          => $nr,
                'name'                   => $name,
                'artikeltyp'             => $artikeltypCode,
                'steuerklasse_id'        => $steuerklasseId,
                'artikel_gruppe_id'      => $artikelGruppeId,
                'einheit_id'             => $einheitId,
                'hersteller_id'          => $herstellerId,
                'kurzbeschreibung'       => $artikel['kurzbeschreibung'] ?: null,
                'beschreibung'           => $artikel['beschreibung'] ?: null,
                'inhalt_menge'           => $artikel['inhalt_menge'],
                'inhalt_einheit'         => $artikel['inhalt_einheit'],
                'herkunftsland'          => null,
                'gewicht_artikel'        => $artikel['gewicht_artikel'],
                'breite'                 => $artikel['breite'],
                'hoehe'                  => $artikel['hoehe'],
                'laenge'                 => $artikel['laenge'],
                'grundpreis_bezugsmenge' => $artikel['grundpreis_bezugsmenge'],
                'grundpreis_anzeigen'    => $artikel['grundpreis_anzeigen'],
                'charge_pflicht'         => $artikel['charge_pflicht'],
                'aktiv'                  => 1,
                'brutto_vk'              => $artikel['brutto_vk'],
                'netto_vk'               => $artikel['netto_vk'],
                'ean_gtin13'             => $artikel['ean'] ?: null,
            ];

            $result = $this->artikelService->save($data);
            if (!$result['erfolg']) {
                $ergebnisse[] = ['artikelnummer' => $nr, 'name' => $name, 'erfolg' => false, 'fehler' => $result['fehler']];
                continue;
            }
            $artikelId = $result['id'];

            if ($artikel['uvp'] !== null) {
                $this->db->prepare("UPDATE artikel SET uvp = :uvp WHERE id = :id")
                    ->execute(['uvp' => $artikel['uvp'], 'id' => $artikelId]);
            }

            $this->fuegeKategorieHinzu($artikelId, $kategorieId);

            Logger::log('jtl_import.normal_anlegen', 'artikel', $artikelId, ['artikelnummer' => $nr]);

            $ergebnisse[] = ['artikelnummer' => $nr, 'name' => $name, 'erfolg' => true, 'id' => $artikelId];
        }

        return $ergebnisse;
    }
}
