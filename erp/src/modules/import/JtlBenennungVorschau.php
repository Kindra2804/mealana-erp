<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/JtlCsvReader.php';
require_once __DIR__ . '/JtlVaterKindImportService.php';
require_once __DIR__ . '/ArtikelBenennung.php';
require_once __DIR__ . '/../hersteller/HerstellerRepository.php';

/**
 * JtlBenennungVorschau – "JTL: neue Nummern" (artikel/jtl_benennung.php), ändert nichts am Artikelstamm.
 *
 * Zwei Stufen, damit die Seite schnell bleibt:
 *  1. basis()     JTL-Stammdaten + Variationskombinationen zerlegen (teuer, 20.000+ Zeilen) und als
 *                 storage/jtl_benennung/basis.json merken – nur neu, wenn eine Datei neuer ist.
 *  2. ergebnis()  bei jedem Aufruf frisch aus der DB: Ausschluss (import_ausschluss, quelle 'jtl'),
 *                 Umbenennung (import_umbenennung), Hersteller + Kürzel (inkl. weitere Namen) -> ArtikelBenennung.
 *                 Ändert Babsi ein Kürzel in der Hersteller-Maske, ist es beim nächsten Laden sichtbar.
 *
 * Achsen werden mit derselben Zerlegung gelesen wie beim JTL-Import (parseVariationskombinationen),
 * damit die Vorschau genau zeigt, was der Import später anlegt.
 */
class JtlBenennungVorschau
{
    public const QUELLE = 'jtl';

    public static function ordner(): string
    {
        return dirname(__DIR__, 3) . '/storage/jtl_benennung';
    }

    public static function pfad(string $art): string
    {
        return self::ordner() . '/' . ($art === 'variationen' ? 'variationen.csv' : 'stammdaten.csv');
    }

    /** Stand der beiden Dateien für die Anzeige (Name, Größe, Datum) oder null. */
    public static function dateiInfo(string $art): ?array
    {
        $p = self::pfad($art);
        return is_file($p) ? ['groesse' => filesize($p), 'zeit' => filemtime($p)] : null;
    }

    /** Stufe 1: zerlegte JTL-Daten (gecacht). */
    public function basis(): array
    {
        $stamm = self::pfad('stammdaten');
        $vari  = self::pfad('variationen');
        if (!is_file($stamm) || !is_file($vari)) {
            throw new RuntimeException('Bitte zuerst beide JTL-Dateien hochladen.');
        }
        $cache = self::ordner() . '/basis.json';
        if (is_file($cache) && filemtime($cache) >= max(filemtime($stamm), filemtime($vari))) {
            $daten = json_decode(file_get_contents($cache), true);
            if (is_array($daten)) return $daten;
        }

        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);
        $ohneRaute = fn(string $s) => str_replace('#', '', trim($s));

        // Stammdaten: Väter, Einzelartikel und Kind-Zugehörigkeit
        $artikel = [];   // jtl_nr => [typ, name, hersteller, vater]
        foreach (JtlCsvReader::lese($stamm) as $z) {
            $nr = $ohneRaute($z['Artikelnummer'] ?? '');
            if ($nr === '') continue;
            $vater = $ohneRaute($z['Identifizierungsspalte Vaterartikel'] ?? '');
            $typ   = ($z['Ist Vaterartikel'] ?? '') === '1' ? 'vater' : ($vater !== '' ? 'kind' : 'einzel');
            $artikel[$nr] = ['typ' => $typ, 'name' => trim($z['Artikelname'] ?? ''),
                             'hersteller' => trim($z['Hersteller'] ?? ''), 'vater' => $vater];
        }

        // Variationskombinationen: Achsen je Kind (gleiche Zerlegung wie der Import)
        $kombis = (new JtlVaterKindImportService())->parseVariationskombinationen($vari);

        $gruppen = [];
        foreach ($artikel as $nr => $a) {
            if ($a['typ'] === 'kind') continue;
            // (string): PHP macht aus Array-Schlüsseln wie "278701" eine Zahl – Nummern bleiben Text
            $gruppen[$nr] = ['typ' => $a['typ'], 'jtl_nr' => (string) $nr, 'name' => $a['name'],
                             'hersteller' => $a['hersteller'], 'kinder' => []];
        }
        foreach ($kombis as $vaterNr => $kinder) {
            $vaterNr = $ohneRaute((string) $vaterNr);
            if (!isset($gruppen[$vaterNr])) {        // Vater neuer als der Stammdaten-Export
                $gruppen[$vaterNr] = ['typ' => 'vater', 'jtl_nr' => $vaterNr, 'name' => $vaterNr,
                                      'hersteller' => '', 'kinder' => [], 'nicht_in_stamm' => true];
            }
            $gruppen[$vaterNr]['typ'] = 'vater';
            foreach ($kinder as $k) {
                $gruppen[$vaterNr]['kinder'][$k['kind_artikelnummer']] = [
                    'jtl_nr' => $k['kind_artikelnummer'], 'name' => $k['kind_name_roh'], 'achsen' => $k['achsen'],
                ];
            }
        }
        // Kinder aus den Stammdaten ohne Variationskombination (keine Achse bekannt)
        foreach ($artikel as $nr => $a) {
            if ($a['typ'] === 'kind' && isset($gruppen[$a['vater']]) && !isset($gruppen[$a['vater']]['kinder'][$nr])) {
                $gruppen[$a['vater']]['kinder'][$nr] = ['jtl_nr' => (string) $nr, 'name' => $a['name'], 'achsen' => []];
            }
        }
        foreach ($gruppen as &$g) $g['kinder'] = array_values($g['kinder']);
        unset($g);
        uksort($gruppen, 'strnatcasecmp');
        $daten = array_values($gruppen);

        if (!is_dir(self::ordner())) mkdir(self::ordner(), 0775, true);
        file_put_contents($cache, json_encode($daten, JSON_UNESCAPED_UNICODE));
        return $daten;
    }

    /**
     * Stufe 2: neue Nummern + Namen. Liefert ['gruppen' => [...], 'summe' => [...]].
     * Je Gruppe: typ, jtl_nr, nr, name, hersteller, hersteller_erp, kuerzel, ausgeschlossen, geprueft, hinweise,
     *            kinder[jtl_nr, nr, name, art, typ, hinweise]
     * $mitAusgeschlossenen: ausgeschlossene Väter/Einzelartikel mitliefern (zum Wieder-Anhaken), sonst nur gezählt.
     */
    public function ergebnis(bool $mitAusgeschlossenen = false): array
    {
        $db = Database::getInstance();
        $aus = array_flip($db->query("SELECT nummer FROM import_ausschluss WHERE quelle = '" . self::QUELLE . "'")->fetchAll(PDO::FETCH_COLUMN));
        $geprueft = array_flip($db->query("SELECT nummer FROM import_geprueft WHERE quelle = '" . self::QUELLE . "'")->fetchAll(PDO::FETCH_COLUMN));
        $umb = $db->query("SELECT nummer_alt, nummer_neu FROM import_umbenennung WHERE quelle = '" . self::QUELLE . "'")->fetchAll(PDO::FETCH_KEY_PAIR);
        // Babsis Vorgaben je Vater/Einzelartikel: fertige Nummer und/oder Hersteller (Migr. 262)
        $vorgabe = [];
        foreach ($db->query("SELECT nummer, nummer_neu, hersteller_id FROM import_vorgabe WHERE quelle = '" . self::QUELLE . "'") as $v) {
            $vorgabe[$v['nummer']] = $v;
        }
        $herstellerIndex = (new HerstellerRepository())->namensIndex(false);
        $hersteller = [];
        foreach ($db->query("SELECT id, name, kuerzel FROM hersteller") as $h) $hersteller[(int) $h['id']] = $h;

        $gruppen = [];
        $summe   = ['vaeter' => 0, 'kinder' => 0, 'einzel' => 0, 'ausgeschlossen' => 0, 'ungeprueft' => 0, 'hinweise' => []];

        foreach ($this->basis() as $g) {
            $istAus = isset($aus[$g['jtl_nr']]);
            if ($istAus) {
                $summe['ausgeschlossen']++;
                if (!$mitAusgeschlossenen) continue;
            }
            // Ausgeschlossen zählt als entschieden; sonst muss die Nummer in import_geprueft stehen
            $istGeprueft = $istAus || isset($geprueft[$g['jtl_nr']]);

            $vg  = $vorgabe[$g['jtl_nr']] ?? null;
            $hId = $g['hersteller'] !== '' ? ($herstellerIndex[mb_strtolower($g['hersteller'])] ?? null) : null;
            $hZugeordnet = $vg && $vg['hersteller_id'] && isset($hersteller[(int) $vg['hersteller_id']]);
            if ($hZugeordnet) $hId = (int) $vg['hersteller_id'];
            $h   = $hId ? $hersteller[$hId] : null;
            $nrFest = $vg && $vg['nummer_neu'] !== null && $vg['nummer_neu'] !== '';
            $vorab = [];
            if ($hZugeordnet)             $vorab[] = 'Hersteller zugeordnet';
            elseif ($g['hersteller'] === '') $vorab[] = 'ohne Hersteller';
            elseif (!$h)                  $vorab[] = 'Hersteller unbekannt';
            if ($nrFest)                  $vorab[] = 'Nummer vorgegeben';
            if (!empty($g['nicht_in_stamm'])) $vorab[] = 'fehlt in Stammdaten-Datei';
            if (!$istGeprueft) $vorab[] = 'noch nicht geprüft';

            $vaterNr   = $nrFest ? $vg['nummer_neu'] : ($umb[$g['jtl_nr']] ?? $g['jtl_nr']);
            $vaterName = JtlVaterKindImportService::kuerzeDropsName($g['name']);
            $kinder    = [];
            foreach ($g['kinder'] as $k) {
                if (isset($aus[$k['jtl_nr']])) { if (!$istAus) $summe['ausgeschlossen']++; continue; }
                $kinder[] = ['jtl_nr' => $k['jtl_nr'], 'nr' => $umb[$k['jtl_nr']] ?? $k['jtl_nr'],
                             'name' => $k['name'], 'achsen' => $k['achsen']];
            }
            if ($g['typ'] === 'vater' && !$kinder && $g['kinder']) {
                // alle Kinder ausgeschlossen, Vater bleibt (z.B. LN-118, neue Farben kommen)
                $vorab[] = 'alle Kinder ausgeschlossen';
            }

            $r = ArtikelBenennung::rechne(['nr' => $vaterNr, 'jtl_nr' => $g['jtl_nr'], 'name' => $vaterName,
                                           'kuerzel' => $h['kuerzel'] ?? null, 'nr_fest' => $nrFest], $kinder);
            if (!$h || empty($h['kuerzel'])) {
                $r['hinweise'] = array_values(array_diff($r['hinweise'], ['Hersteller ohne Kürzel – Nummern bleiben']));
                if ($h) $vorab[] = 'Hersteller ohne Kürzel';
            }
            foreach ($r['kinder'] as $i => &$k) $k['jtl_nr'] = $kinder[$i]['jtl_nr'];
            unset($k);

            $gruppen[] = [
                'typ' => $g['typ'], 'jtl_nr' => $g['jtl_nr'], 'nr' => $r['nr'],
                'name' => $g['typ'] === 'einzel' ? $g['name'] : $vaterName,
                'hersteller' => $g['hersteller'], 'hersteller_erp' => $h['name'] ?? null, 'kuerzel' => $h['kuerzel'] ?? null,
                'hersteller_id' => $hId, 'vorgabe_nr' => $nrFest ? $vg['nummer_neu'] : null,
                'vorgabe_hersteller_id' => $hZugeordnet ? (int) $vg['hersteller_id'] : null,
                'ausgeschlossen' => $istAus, 'geprueft' => $istGeprueft,
                'hinweise' => array_merge($vorab, $r['hinweise']), 'kinder' => $r['kinder'],
            ];
            if ($istAus) continue;
            $summe[$g['typ'] === 'einzel' ? 'einzel' : 'vaeter']++;
            $summe['kinder'] += count($r['kinder']);
            if (!$istGeprueft) $summe['ungeprueft']++;
        }

        // Nummer kommt bei verschiedenen Artikeln vor (über alle Väter hinweg)
        $alle = [];
        foreach ($gruppen as $g) {
            if ($g['ausgeschlossen']) continue;
            $alle[] = $g['nr'];
            foreach ($g['kinder'] as $k) $alle[] = $k['nr'];
        }
        $zaehl = array_count_values($alle);
        foreach ($gruppen as &$g) {
            if ($g['ausgeschlossen']) continue;
            if ($zaehl[$g['nr']] > 1) $g['hinweise'][] = 'Nummer bei anderem Artikel';
            foreach ($g['kinder'] as &$k) {
                if ($zaehl[$k['nr']] > 1 && !in_array('Nummer doppelt', $k['hinweise'], true)) $k['hinweise'][] = 'Nummer bei anderem Artikel';
                foreach ($k['hinweise'] as $t) $summe['hinweise'][$t] = ($summe['hinweise'][$t] ?? 0) + 1;
            }
            unset($k);
            foreach ($g['hinweise'] as $t) $summe['hinweise'][$t] = ($summe['hinweise'][$t] ?? 0) + 1;
        }
        unset($g);
        arsort($summe['hinweise']);

        return ['gruppen' => $gruppen, 'summe' => $summe];
    }

    /**
     * Babsis Entscheidung je Vater/Einzelartikel: übernehmen (= aus import_ausschluss entfernen) oder nicht.
     * Kinder folgen dem Vater. Gilt danach auch als geprüft.
     */
    public function setzeAuswahl(string $nummer, string $name, bool $uebernehmen, ?int $benutzerId): void
    {
        $db = Database::getInstance();
        if ($uebernehmen) {
            $db->prepare("DELETE FROM import_ausschluss WHERE quelle = ? AND nummer = ?")->execute([self::QUELLE, $nummer]);
        } else {
            $db->prepare("INSERT INTO import_ausschluss (quelle, nummer, name, benutzer_id) VALUES (?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE name = VALUES(name), benutzer_id = VALUES(benutzer_id)")
               ->execute([self::QUELLE, $nummer, mb_substr($name, 0, 255), $benutzerId]);
        }
        $this->markiereGeprueft([$nummer], $benutzerId);
    }

    /**
     * Babsis Vorgabe für einen Vater/Einzelartikel: fertige neue Nummer und/oder Hersteller.
     * Leere Nummer bzw. null = nichts vorgeben; sind beide leer, wird die Vorgabe gelöscht (Schema gilt wieder).
     * Gilt danach als geprüft.
     */
    public function setzeVorgabe(string $nummer, ?string $nummerNeu, ?int $herstellerId, ?int $benutzerId): void
    {
        $nummerNeu = $nummerNeu !== null ? trim($nummerNeu) : '';
        if ($nummerNeu !== '') {
            if (preg_match('/\s/u', $nummerNeu)) throw new RuntimeException('Die Nummer darf keine Leerzeichen enthalten.');
            if (strlen($nummerNeu) > ArtikelBenennung::MAX_NR) throw new RuntimeException('Die Nummer ist länger als ' . ArtikelBenennung::MAX_NR . ' Zeichen.');
        }
        $db = Database::getInstance();
        if ($herstellerId) {
            $st = $db->prepare("SELECT COUNT(*) FROM hersteller WHERE id = ?");
            $st->execute([$herstellerId]);
            if (!$st->fetchColumn()) throw new RuntimeException('Hersteller nicht gefunden.');
        }
        if ($nummerNeu === '' && !$herstellerId) {
            $db->prepare("DELETE FROM import_vorgabe WHERE quelle = ? AND nummer = ?")->execute([self::QUELLE, $nummer]);
        } else {
            $db->prepare("INSERT INTO import_vorgabe (quelle, nummer, nummer_neu, hersteller_id, benutzer_id) VALUES (?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE nummer_neu = VALUES(nummer_neu), hersteller_id = VALUES(hersteller_id), benutzer_id = VALUES(benutzer_id)")
               ->execute([self::QUELLE, $nummer, $nummerNeu !== '' ? $nummerNeu : null, $herstellerId ?: null, $benutzerId]);
        }
        $this->markiereGeprueft([$nummer], $benutzerId);
    }

    /** Nummern als "angeschaut" merken (Knopf "Seite als geprüft markieren"). */
    public function markiereGeprueft(array $nummern, ?int $benutzerId): int
    {
        $st = Database::getInstance()->prepare("INSERT IGNORE INTO import_geprueft (quelle, nummer, benutzer_id) VALUES (?, ?, ?)");
        $n = 0;
        foreach ($nummern as $nr) {
            $nr = trim((string) $nr);
            if ($nr === '') continue;
            $st->execute([self::QUELLE, $nr, $benutzerId]);
            $n += $st->rowCount();
        }
        return $n;
    }
}
