<?php
/**
 * Lang-Yarns-Import (Artikelstamm-CSV aus dem Lang-Händlerportal).
 *
 * Aufbau der Datei (Strichpunkt, Windows-1252, am Ende hängt HTML der Webshop-Seite -> wird ignoriert):
 *   Status 99      = Vater ("Definitionsartikel"), Nummer "749"
 *   Kinder         = "749.0003" -> bei uns LY-749-0003, Original-Nummer = Lief-Art.-Nr.
 *   Status 35/40/55/61 = nicht importieren; schon vorhanden -> Auslaufartikel
 *   EP/KG          = EK je kg (Garn: x Knäuelgewicht = EK je Knäuel; ohne Gewicht = EK je Stück)
 *   VP/KN          = UVP je Knäuel/Stück (brutto)
 *
 * Entscheidungen Jacky 2026-11-16:
 *   - nur Hauptgruppe 10 (Garne) + 30 (Bücher/Anleitungen), Zolltarifnummer mit
 *   - EK + UVP immer aus der Datei, Verkaufspreis nur bei neuen Artikeln
 *   - neue Artikel aktiv, ohne Shop
 *   - LY-Artikel im ERP, die nicht (mehr) in der Datei stehen: nur anzeigen
 * Bücher: Sprachfassungen sind in der Datei Kinder -> bei uns Einzelartikel, nur deutsche Fassungen.
 *
 * Ablauf: plan() = Probelauf ohne Änderung; ausfuehren() verarbeitet einen Abschnitt der Väter
 * (die Seite ruft es mehrmals auf -> kein Timeout bei tausenden Artikeln).
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/LangImportRepository.php';
require_once __DIR__ . '/../artikel/ArtikelRepository.php';
require_once __DIR__ . '/../artikel/ArtikelService.php';
require_once __DIR__ . '/../varianten/VariantenService.php';
require_once __DIR__ . '/../varianten/VariantenRepository.php';
require_once __DIR__ . '/../merkmale/MerkmalService.php';
require_once __DIR__ . '/../lager/LagerService.php';

class LangImportService
{
    public const AUSLAUF_STATUS = ['35', '40', '55', '61'];
    public const HAUPTGRUPPEN   = ['10' => 'garn', '30' => 'buch'];
    public const PRAEFIX        = 'LY-';

    // Vorgaben für neue Artikel (wie die vorhandenen Lang-Artikel)
    private const GARN = ['artikeltyp_id' => 1, 'steuerklasse_id' => 1, 'artikel_gruppe_id' => 1, 'einheit_id' => 1, 'satz' => 20.0];
    private const BUCH = ['artikeltyp_id' => 6, 'steuerklasse_id' => 2, 'artikel_gruppe_id' => 8, 'einheit_id' => 4, 'satz' => 10.0];

    private PDO $db;
    private LangImportRepository $repo;
    private ArtikelRepository $artikelRepo;
    private ArtikelService $artikelService;
    private VariantenService $variantenService;
    private VariantenRepository $variantenRepo;
    private MerkmalService $merkmale;
    private LagerService $lager;

    private array $lyMap  = [];
    private array $eanMap = [];
    private array $ausschluss = [];   // abgewählte Lang-Väter (Migration 254)
    private ?int $farbAchseId = null;

    public function __construct()
    {
        $this->db               = Database::getInstance();
        $this->repo             = new LangImportRepository();
        $this->artikelRepo      = new ArtikelRepository();
        $this->artikelService   = new ArtikelService();
        $this->variantenService = new VariantenService();
        $this->variantenRepo    = new VariantenRepository();
        $this->merkmale         = new MerkmalService();
        $this->lager            = new LagerService();
    }

    // ══════════════════════════════════════════════════════════════════
    //  Datei lesen
    // ══════════════════════════════════════════════════════════════════

    /**
     * @return array{vaeter: array, kinder: array, alle_nummern: array, zeilen: int, rest: int}
     *   vaeter[nr] = Zeile, kinder[vaterNr][kindNr] = Zeile (nur Hauptgruppen 10 + 30),
     *   alle_nummern = LY-Nummern ALLER Zeilen (für „nicht mehr in der Datei“)
     */
    public function lies(string $pfad): array
    {
        $f = @fopen($pfad, 'r');
        if (!$f) throw new RuntimeException('Datei kann nicht gelesen werden.');
        $kopf = fgetcsv($f, 0, ';', '"', '');
        if (!$kopf || ($kopf[0] ?? '') !== 'HGrp' || ($kopf[1] ?? '') !== 'ArtNr') {
            fclose($f);
            throw new RuntimeException('Das ist keine Lang-Artikelstamm-Datei (erste Spalten müssen „HGrp;ArtNr“ sein).');
        }
        $kopf = array_map(fn($k) => mb_convert_encoding($k, 'UTF-8', 'Windows-1252'), $kopf);
        $anz  = count($kopf);

        $vaeter = []; $kinder = []; $alle = []; $zeilen = 0; $rest = 0;
        while (($r = fgetcsv($f, 0, ';', '"', '')) !== false) {
            if (count($r) < $anz - 2 || !preg_match('/^\d+(\.\d+)?$/', $r[1] ?? '')) { $rest++; continue; }   // HTML am Dateiende
            $zeilen++;
            $r = array_map(fn($v) => trim(mb_convert_encoding($v, 'UTF-8', 'Windows-1252')), array_slice(array_pad($r, $anz, ''), 0, $anz));
            $z = array_combine($kopf, $r);
            $nr = $z['ArtNr'];
            $alle[self::lyNummer($nr)] = true;
            if (!isset(self::HAUPTGRUPPEN[$z['HGrp']])) continue;
            if ($z['Status'] === '99') {
                $vaeter[$nr] = $z;
            } elseif (str_contains($nr, '.')) {
                $kinder[strstr($nr, '.', true)][$nr] = $z;
            }
        }
        fclose($f);
        ksort($vaeter, SORT_NATURAL);
        return ['vaeter' => $vaeter, 'kinder' => $kinder, 'alle_nummern' => $alle, 'zeilen' => $zeilen, 'rest' => $rest];
    }

    public static function lyNummer(string $artNr): string
    {
        return self::PRAEFIX . str_replace('.', '-', $artNr);
    }

    /** Väter, bei denen etwas zu tun ist (haben Kinder in der Datei) -- feste Reihenfolge für die Abschnitte. */
    public function vaterListe(array $datei): array
    {
        return array_values(array_filter(array_keys($datei['vaeter']), fn($v) => !empty($datei['kinder'][$v])));
    }

    // ══════════════════════════════════════════════════════════════════
    //  Probelauf
    // ══════════════════════════════════════════════════════════════════

    public function plan(string $pfad, int $lieferantId): array
    {
        $datei = $this->lies($pfad);
        $this->ladeMaps();
        $ekMap = $this->repo->ladeEkMap($lieferantId);
        $vkMap = $this->repo->ladeVkMap();

        $summe = ['vaeter_neu' => 0, 'kinder_neu' => 0, 'einzel_neu' => 0, 'aktualisieren' => 0, 'ean_treffer' => 0,
                  'auslauf' => 0, 'schon_auslauf' => 0, 'uebersprungen' => 0, 'nummer_bereinigt' => 0, 'ohne_preis' => 0,
                  'abgewaehlt' => 0, 'ek_hoeher' => 0, 'ek_niedriger' => 0, 'vk_abweichend' => 0, 'ean_umzug' => 0];
        $listen = ['auslauf' => [], 'ean_treffer' => [], 'ean_umzug' => [], 'uebersprungen' => []];
        $auswahl = [];    // Väter mit neuen Artikeln -> im Probelauf abwählbar
        $preise  = [];    // gruppiert je Vater + gleiche Preise
        $gefunden = [];   // artikel_id => true (in der Datei gefunden)

        foreach ($this->vaterListe($datei) as $vnr) {
            $p = $this->planeVater($vnr, $datei);
            if ($p['vater']['aktion'] === 'neu') $summe['vaeter_neu']++;
            if ($p['vater']['id']) $gefunden[$p['vater']['id']] = true;
            $neu = []; $abgewaehlt = 0;
            foreach ($p['kinder'] as $k) {
                if ($k['id']) $gefunden[$k['id']] = true;
                switch ($k['aktion']) {
                    case 'neu':
                        $summe[$p['typ'] === 'buch' ? 'einzel_neu' : 'kinder_neu']++;
                        if ($k['vk'] <= 0) $summe['ohne_preis']++;
                        if ($k['ean_umzug']) {
                            $summe['ean_umzug']++;
                            $listen['ean_umzug'][] = [$k['nr'], $k['ean_umzug']['nr'], $k['ean_umzug']['name'], $k['ean_umzug']['bestand']];
                        }
                        $neu[] = $k;
                        break;
                    case 'abgewaehlt':
                        $summe['abgewaehlt']++;
                        $neu[] = $k; $abgewaehlt++;
                        break;
                    case 'aktualisieren':
                        $summe['aktualisieren']++;
                        if ($k['neue_nummer']) $summe['nummer_bereinigt']++;
                        if ($k['ean_treffer']) { $summe['ean_treffer']++; $listen['ean_treffer'][] = [$k['nr'], $k['erp_nr'], $k['erp_name']]; }
                        $this->preisVergleich($p, $k, $ekMap[$k['id']] ?? 0.0, $vkMap[$k['id']] ?? 0.0, $preise, $summe);
                        break;
                    case 'auslauf':
                        $summe['auslauf']++;
                        $listen['auslauf'][] = [$k['erp_nr'], $k['erp_name'], $k['status'], (float) $k['bestand']];
                        break;
                    case 'schon_auslauf':
                        $summe['schon_auslauf']++;
                        break;
                    case 'ueberspringen':
                        $summe['uebersprungen']++;
                        $listen['uebersprungen'][] = [$k['nr'], $k['name'], $k['grund']];
                        break;
                }
            }
            if ($neu) $auswahl[] = $this->auswahlZeile($p, $neu, $abgewaehlt > 0);
        }

        // LY-Artikel im ERP, die in der Datei gar nicht vorkommen (auch nicht über die EAN)
        $nichtInDatei = []; $nichtIds = [];
        foreach ($this->lyMap as $nr => $a) {
            if (isset($datei['alle_nummern'][$nr]) || isset($gefunden[(int) $a['id']])) continue;
            $nichtIds[] = (int) $a['id'];
            $nichtInDatei[] = [$a['artikelnummer'], $a['name'], (float) $a['bestand'], (int) $a['aktiv'], (int) $a['ist_auslaufartikel']];
        }
        usort($nichtInDatei, fn($a, $b) => [$b[2] > 0, $a[0]] <=> [$a[2] > 0, $b[0]]);   // mit Bestand zuerst
        $preise = array_values($preise);
        usort($preise, fn($a, $b) => (abs($b['ek_prozent'] ?? 0) <=> abs($a['ek_prozent'] ?? 0)) ?: strnatcmp($a['nr'], $b['nr']));

        return [
            'zeilen' => $datei['zeilen'], 'rest' => $datei['rest'], 'vaeter' => count($this->vaterListe($datei)),
            'summe' => $summe, 'listen' => $listen, 'auswahl' => $auswahl, 'preise' => $preise,
            'nicht_in_datei' => $nichtInDatei,
            // Strecke nur noch für Artikel aus der Liste (Jacky 2026-11-16)
            'strecke' => [
                'allgemein'      => $this->repo->lieferantStreckeAllgemein($lieferantId),
                'zuruecksetzen'  => $this->repo->mitStreckeJa($lieferantId, $nichtIds),
            ],
        ];
    }

    /** Eine Zeile der Väter-Auswahl (Babsi kann schlechte Garne abwählen) */
    private function auswahlZeile(array $p, array $neu, bool $abgewaehlt): array
    {
        $v  = $p['zeile'];
        $g  = self::zahl($v['KnGWEDI']) * 1000;
        return [
            'vnr' => $p['vnr'], 'nr' => $p['vater']['nr'],
            'name' => $p['typ'] === 'buch' ? preg_replace('/^FAM\s+/u', 'Fatto a Mano ', $v['Bez_1']) : $p['vater']['name'],
            'typ' => $p['typ'], 'vorhanden' => (bool) $p['vater']['id'], 'neu' => count($neu), 'abgewaehlt' => $abgewaehlt,
            'zusammensetzung' => $v['Zusammensetzung_D'],
            'lauflaenge' => ($g > 0 && $g < 5000 && (int) $v['LfLg'] > 0) ? self::de($g) . ' g = ' . (int) $v['LfLg'] . ' m' : '',
            'nadel' => str_replace('.', ',', $v['Nadelstaerke']), 'vk' => self::haeufigsterPreis($neu),
        ];
    }

    /** EK geändert (Preiserhöhung/-senkung bei Lang) oder unser VK ≠ Lang-Verkaufspreis -> Preisliste, gruppiert je Vater */
    private function preisVergleich(array $p, array $k, float $ekAlt, float $vkAlt, array &$preise, array &$summe): void
    {
        $ekNeu = $this->ek($k['zeile'], $p['zeile']);
        $vp    = $k['vk'];
        $ekGeaendert = $ekAlt > 0 && $ekNeu !== null && abs($ekNeu - $ekAlt) >= 0.005;
        $vkAnders    = $vp > 0 && $vkAlt > 0 && abs($vp - $vkAlt) >= 0.005;
        if (!$ekGeaendert && !$vkAnders) return;

        if ($ekGeaendert) $summe[$ekNeu > $ekAlt ? 'ek_hoeher' : 'ek_niedriger']++;
        if ($vkAnders) $summe['vk_abweichend']++;

        $schluessel = $p['vnr'] . '|' . $ekAlt . '|' . ($ekGeaendert ? $ekNeu : '') . '|' . $vkAlt . '|' . ($vkAnders ? $vp : '');
        if (!isset($preise[$schluessel])) {
            $preise[$schluessel] = [
                'nr'   => $p['typ'] === 'buch' ? $k['erp_nr'] : $p['vater']['nr'],
                'name' => $p['typ'] === 'buch' ? $k['erp_name'] : $p['vater']['name'],
                'anzahl' => 0, 'ek_alt' => $ekAlt, 'ek_neu' => $ekGeaendert ? $ekNeu : null,
                'ek_prozent' => $ekGeaendert ? round(($ekNeu - $ekAlt) / $ekAlt * 100, 1) : null,
                'vk' => $vkAlt, 'vp' => $vkAnders ? $vp : null,
            ];
        }
        $preise[$schluessel]['anzahl']++;
    }

    /** Für die Seite: Vater ab-/anwählen (bleibt gespeichert) */
    public function setzeAbwahl(string $vnr, string $name, bool $abwaehlen, ?int $benutzerId): void
    {
        if (!preg_match('/^\d+$/', $vnr)) throw new RuntimeException('Ungültige Nummer');
        $this->repo->setzeAusschluss('lang', $vnr, $name, $benutzerId, $abwaehlen);
        Logger::log($abwaehlen ? 'import.lang_abgewaehlt' : 'import.lang_angewaehlt', 'import_ausschluss', null,
            ['nummer' => $vnr, 'name' => $name], $benutzerId ?? $this->jarvisId());
    }

    /**
     * Was passiert mit einem Vater und seinen Kindern? (ändert nichts)
     * kinder[].aktion: neu | aktualisieren | auslauf | schon_auslauf | ueberspringen | nichts
     */
    private function planeVater(string $vnr, array $datei): array
    {
        $v    = $datei['vaeter'][$vnr];
        $typ  = self::HAUPTGRUPPEN[$v['HGrp']];
        $vLy  = self::lyNummer($vnr);
        $vErp = $typ === 'garn' ? ($this->lyMap[$vLy] ?? null) : null;

        $plan = [
            'vnr' => $vnr, 'typ' => $typ, 'zeile' => $v,
            'vater' => ['nr' => $vLy, 'id' => $vErp ? (int) $vErp['id'] : null, 'aktion' => $typ === 'buch' ? 'keiner' : ($vErp ? 'vorhanden' : 'neu'),
                        'name' => $vErp['name'] ?? $v['Bez_1']],
            'kinder' => [],
        ];

        // Kann ein vorhandener Vater neue Kinder bekommen?
        $sperre = null;
        if ($vErp) {
            if ($vErp['vaterartikel_id']) $sperre = 'Vaternummer ist im ERP selbst ein Kind';
            elseif ($this->repo->findLagerbestandZeilenUngleichNull((int) $vErp['id']) > 0) $sperre = 'Vater hat eigenen Bestand – zuerst an eine Variante übergeben';
            else {
                $achsen = $this->repo->findAchsenIds((int) $vErp['id']);
                if ($achsen && $achsen !== [$this->farbAchse()]) $sperre = 'Vater hat andere Achsen als nur „Farbe“';
            }
        }

        foreach ($datei['kinder'][$vnr] as $knr => $k) {
            $ly   = self::lyNummer($knr);
            $erp  = $this->lyMap[$ly] ?? null;
            $eanTreffer = false;
            $ean  = self::ean($k['EAN_Artikel']);
            $eanUmzug = null;
            if (!$erp && $ean && isset($this->eanMap[$ean])) {
                $erp = $this->repo->findArtikelKurz($this->eanMap[$ean]);
                $eanTreffer = (bool) $erp;
                // Hängt der EAN-Treffer im ERP an einem ANDEREN Lang-Garn (z. B. LY-945 LUSSO, Lang sagt 845 MILLE
                // COLORI BABY)? Dann ist die EAN dort falsch eingetragen (Jacky 2026-11-17): Artikel so anlegen,
                // wie er in der Lang-Liste steht, und die EAN vom alten zum neuen Artikel umziehen.
                if ($erp && $typ === 'garn') {
                    $garnNr = $erp['vaterartikel_id'] ? $this->repo->artikelnummer((int) $erp['vaterartikel_id']) : $erp['artikelnummer'];
                    $gleichesGarn = $garnNr === $vLy || str_starts_with((string) $garnNr, $vLy . '-');
                    if (!$gleichesGarn && str_starts_with((string) $garnNr, 'LY-')) {
                        $eanUmzug = ['id' => (int) $erp['id'], 'nr' => $erp['artikelnummer'], 'name' => $erp['name'], 'bestand' => (float) $erp['bestand']];
                        $erp = null;
                        $eanTreffer = false;
                    }
                }
            }
            $aus  = in_array($k['Status'], self::AUSLAUF_STATUS, true);
            $vk   = self::zahl($k['VP/KN']);
            $kind = [
                'nr' => $ly, 'orig' => $knr, 'zeile' => $k, 'status' => $k['Status'] . ' ' . $k['StatusBez'],
                'id' => $erp ? (int) $erp['id'] : null, 'erp_nr' => $erp['artikelnummer'] ?? null, 'erp_name' => $erp['name'] ?? null,
                'bestand' => $erp['bestand'] ?? 0, 'ean_treffer' => $eanTreffer, 'vk' => $vk,
                'name' => $this->kindName($typ, $plan['vater']['name'], $knr, $k), 'neue_nummer' => null, 'grund' => null,
                'ean_umzug' => $eanUmzug,
            ];

            if ($erp) {
                if ($aus) {
                    $kind['aktion'] = (int) $erp['ist_auslaufartikel'] ? 'schon_auslauf' : 'auslauf';
                } else {
                    $kind['aktion'] = 'aktualisieren';
                    if (!$eanTreffer && $erp['artikelnummer'] !== $ly && !$this->repo->nummerVergeben($ly, (int) $erp['id'])) $kind['neue_nummer'] = $ly;
                }
            } elseif ($aus) {
                $kind['aktion'] = 'nichts';
            } elseif ($typ === 'buch' && !self::istDeutscheFassung($k)) {
                $kind['aktion'] = 'nichts';      // EN/DK/IT/… Fassungen gar nicht anlegen
            } elseif ($typ === 'buch' && (!$ean || self::zahl($k['EP/KG']) <= 0)) {
                $kind['aktion'] = 'nichts';      // Gratis-Flyer, Staffel-Einträge ohne EAN
            } elseif (isset($this->ausschluss[$vnr])) {
                $kind['aktion'] = 'abgewaehlt';  // Babsi hat das Garn abgewählt
            } elseif ($sperre) {
                $kind['aktion'] = 'ueberspringen';
                $kind['grund']  = $sperre;
            } elseif ($ean && !$eanUmzug && $this->repo->eanVergeben($ean)) {
                $kind['aktion'] = 'ueberspringen';
                $kind['grund']  = "EAN $ean gehört schon einem anderen Artikel";
            } else {
                $kind['aktion'] = 'neu';
            }
            $plan['kinder'][] = $kind;
        }

        if ($plan['vater']['aktion'] === 'neu' && !array_filter($plan['kinder'], fn($k) => $k['aktion'] === 'neu')) {
            $plan['vater']['aktion'] = 'keiner';   // kein neues Kind -> Vater nicht anlegen
        }
        return $plan;
    }

    // ══════════════════════════════════════════════════════════════════
    //  Ausführen (ein Abschnitt)
    // ══════════════════════════════════════════════════════════════════

    /**
     * Verarbeitet die Väter $von .. $von+$anzahl-1 der vaterListe().
     * @return array{erfolg: bool, gesamt: int, bis: int, ergebnis: array, fehler: array}
     */
    public function ausfuehren(string $pfad, int $lieferantId, int $von, int $anzahl, ?int $benutzerId): array
    {
        if ($lieferantId <= 0) throw new RuntimeException('Bitte den Lieferanten wählen.');
        $datei = $this->lies($pfad);
        $liste = $this->vaterListe($datei);
        $this->ladeMaps();
        $benutzerId = $benutzerId ?? $this->jarvisId();

        $erg = ['vaeter_neu' => 0, 'kinder_neu' => 0, 'einzel_neu' => 0, 'aktualisiert' => 0, 'auslauf' => 0, 'uebersprungen' => 0];
        $fehler = [];
        foreach (array_slice($liste, $von, $anzahl) as $vnr) {
            // Je Vater eine Transaktion; läuft schon eine (Test), dann ein Sicherungspunkt
            $aussen = $this->db->inTransaction();
            $aussen ? $this->db->exec('SAVEPOINT lang_vater') : $this->db->beginTransaction();
            try {
                $plan = $this->planeVater($vnr, $datei);
                $this->fuehreVaterAus($plan, $lieferantId, $benutzerId, $erg);
                $aussen ? $this->db->exec('RELEASE SAVEPOINT lang_vater') : $this->db->commit();
            } catch (Throwable $e) {
                $aussen ? $this->db->exec('ROLLBACK TO SAVEPOINT lang_vater') : $this->db->rollBack();
                $fehler[] = self::lyNummer($vnr) . ': ' . $e->getMessage();
            }
        }

        // Einmal (erster Abschnitt): Strecke nur noch für markierte Artikel -- Lieferant umstellen,
        // händisches „Strecke ja“ bei Lang-Artikeln, die nicht mehr in der Liste stehen, zurücknehmen
        if ($von === 0) {
            $st = $this->plan($pfad, $lieferantId)['strecke'];
            if ($st['allgemein']) {
                $this->repo->lieferantStreckeNurMarkierte($lieferantId);
                Logger::log('lieferant.strecke_nur_markierte', 'lieferanten', $lieferantId, ['quelle' => 'lang_import'], $benutzerId);
            }
            $this->repo->streckeZuruecksetzen($lieferantId, $st['zuruecksetzen']);
            $erg['strecke_zurueckgesetzt'] = count($st['zuruecksetzen']);
        }

        Logger::log('import.lang', 'lieferanten', $lieferantId, ['abschnitt' => "$von-" . ($von + $anzahl - 1)] + $erg + ['fehler' => count($fehler)], $benutzerId);
        return ['erfolg' => true, 'gesamt' => count($liste), 'bis' => min($von + $anzahl, count($liste)), 'ergebnis' => $erg, 'fehler' => $fehler];
    }

    private function fuehreVaterAus(array $plan, int $lieferantId, int $benutzerId, array &$erg): void
    {
        $v       = $plan['zeile'];
        $neue    = array_values(array_filter($plan['kinder'], fn($k) => $k['aktion'] === 'neu'));
        $vaterId = $plan['vater']['id'];

        // ── Bücher: deutsche Fassungen als Einzelartikel ──
        if ($plan['typ'] === 'buch') {
            foreach ($neue as $k) {
                $id = $this->artikelRepo->insert($this->stammDaten('buch', $k['nr'], $k['name'], $v, $k['zeile'], null));
                $this->nachbearbeiten($id, $k, $lieferantId, 'buch', true);
                $erg['einzel_neu']++;
            }
        } else {
            // ── Garn: Vater anlegen bzw. vorbereiten, Kinder über den Varianten-Generator ──
            if ($neue) {
                // EAN-Umzug: falsch eingetragene EAN beim alten Artikel (anderes Garn) entfernen,
                // nachbearbeiten() setzt sie dann beim neu angelegten Kind
                foreach ($neue as $k) {
                    if (!$k['ean_umzug']) continue;
                    $ean = self::ean($k['zeile']['EAN_Artikel']);
                    $this->repo->entferneEan($k['ean_umzug']['id'], $ean);
                    Logger::log('artikel.ean_umgezogen', 'artikel', $k['ean_umzug']['id'],
                        ['ean' => $ean, 'von' => $k['ean_umzug']['nr'], 'nach' => $k['nr'], 'quelle' => 'lang_import'], $benutzerId);
                }
                if (!$vaterId) {
                    $vaterId = $this->artikelRepo->insert($this->stammDaten('garn', $plan['vater']['nr'], $v['Bez_1'], $v, $neue[0]['zeile'], null));
                    $this->repo->setzeLieferantZeile($vaterId, $lieferantId, $plan['vnr'], $this->ek($neue[0]['zeile'], $v), null);
                    $this->repo->setzeDropshipping($vaterId, $lieferantId, 1);
                    $vk = self::haeufigsterPreis($neue);
                    if ($vk > 0) $this->repo->setzeBasisPreis($vaterId, $vk, round($vk / 1.2, 2));
                    $this->setzeMerkmale($vaterId, $v);
                    Logger::log('artikel.anlegen', 'artikel', $vaterId, ['artikelnummer' => $plan['vater']['nr'], 'quelle' => 'lang_import'], $benutzerId);
                    $erg['vaeter_neu']++;
                } else {
                    // Vorhandener Vater bekommt neue Farben -> wieder aktiv + kein Auslauf mehr
                    $this->artikelRepo->removeAuslauf($vaterId);
                    $this->artikelRepo->setArtikelAktiv($vaterId, 1);
                }
                $this->legeKinderAn($vaterId, $plan['vater']['name'], $neue, $lieferantId, $erg);
            }
            if ($vaterId && $plan['vater']['aktion'] === 'vorhanden') {
                $kind1 = $plan['kinder'][0]['zeile'];
                $this->repo->setzeLieferantZeile($vaterId, $lieferantId, $plan['vnr'], $this->ek($kind1, $v), null);
                if ($plan['typ'] === 'garn') $this->repo->setzeDropshipping($vaterId, $lieferantId, 1);
            }
        }

        // ── vorhandene Artikel: aktualisieren bzw. Auslauf ──
        foreach ($plan['kinder'] as $k) {
            if ($k['aktion'] === 'aktualisieren') {
                $this->nachbearbeiten($k['id'], $k, $lieferantId, $plan['typ'], false);
                $erg['aktualisiert']++;
            } elseif ($k['aktion'] === 'auslauf') {
                $this->repo->setzeAuslauf($k['id']);
                $this->repo->setzeDropshipping($k['id'], $lieferantId, 0);   // Lang liefert nicht mehr per Strecke
                $this->lager->pruefAuslaufartikelStatus($k['id'], (float) $k['bestand']);   // ohne Bestand -> inaktiv
                Logger::log('artikel.auslauf.setzen', 'artikel', $k['id'], ['quelle' => 'lang_import', 'status' => $k['status']], $benutzerId);
                $erg['auslauf']++;
            } elseif ($k['aktion'] === 'ueberspringen') {
                $erg['uebersprungen']++;
            }
        }
        // Alle Farben im Auslauf -> Vater auch
        if ($vaterId && $this->repo->zaehleKinder($vaterId) > 0 && $this->repo->zaehleKinderOhneAuslauf($vaterId) === 0) {
            $this->repo->setzeAuslauf($vaterId);
        }
    }

    /** Neue Farben: Achse „Farbe“ + Werte am Vater, Kinder über VariantenService (erben Stammdaten, Merkmale, Lieferant, Preis). */
    private function legeKinderAn(int $vaterId, string $vaterName, array $neue, int $lieferantId, array &$erg): void
    {
        $achse = $this->farbAchse();
        if (!in_array($achse, $this->repo->findAchsenIds($vaterId), true)) {
            $this->variantenRepo->insertArtikelAchse(['artikel_id' => $vaterId, 'achse_id' => $achse, 'sort_order' => 0]);
        }
        $werte = $this->repo->findWerte($vaterId, $achse);
        $sort  = $this->repo->maxWertSort($vaterId, $achse);

        $kombis = [];
        foreach ($neue as $k) {
            $wert = self::wertText($k['orig'], $k['zeile']);
            $key  = mb_strtolower($wert);
            if (!isset($werte[$key])) {
                $werte[$key] = $this->variantenRepo->insertWert(['artikel_id' => $vaterId, 'achse_id' => $achse, 'wert' => $wert, 'sort_order' => $sort += 10]);
            }
            $kombis[] = ['key' => (string) $werte[$key], 'artikelnummer' => $k['nr'], 'name' => $k['name']];
        }

        $vater = $this->artikelRepo->findById($vaterId);
        $res   = $this->variantenService->erstelleKombinationen($vater, true, $kombis);
        $this->artikelService->kopiereVaterRelationenZuKindern($vaterId, $res['ids'], $res['preisAnpassungen']);

        foreach ($res['ids'] as $i => $kindId) {
            $this->nachbearbeiten($kindId, $neue[$i], $lieferantId, 'garn', true);
            $erg['kinder_neu']++;
        }
    }

    /**
     * Lieferant (Lief-Art.-Nr. + EK), UVP, Zolltarifnummer, EAN; bei neuen Artikeln auch der Verkaufspreis.
     * Bestehende Verkaufspreise bleiben unangetastet (Jacky).
     */
    private function nachbearbeiten(int $artikelId, array $k, int $lieferantId, string $typ, bool $istNeu): void
    {
        $z   = $k['zeile'];
        $vpe = self::ean($z['EAN_Verp']);
        $this->repo->setzeLieferantZeile($artikelId, $lieferantId, $k['orig'], $this->ek($z, null), $vpe);
        if ($typ === 'garn') $this->repo->setzeDropshipping($artikelId, $lieferantId, 1);   // Strecke möglich (Jacky)

        $vk = $k['vk'];
        if ($istNeu && $vk > 0) {
            $satz = $typ === 'buch' ? self::BUCH['satz'] : self::GARN['satz'];
            $this->repo->setzeBasisPreis($artikelId, $vk, round($vk / (1 + $satz / 100), 2));
        }

        $geaendert = $this->repo->aktualisiereStamm($artikelId, null, self::taric($z['Zollnummer_DE']), $istNeu ? null : $k['neue_nummer']);

        $ean = self::ean($z['EAN_Artikel']);
        if ($ean && !$this->repo->hatGtin($artikelId) && !$this->repo->eanVergeben($ean)) {
            $this->repo->fuegeGtinHinzu($artikelId, $ean);
            $geaendert = true;
        }
        if ($geaendert && !$istNeu) $this->repo->fuerShopMarkieren($artikelId);
    }

    // ══════════════════════════════════════════════════════════════════
    //  Stammdaten + Merkmale für neue Artikel
    // ══════════════════════════════════════════════════════════════════

    private function stammDaten(string $typ, string $nr, string $name, array $v, array $kind, ?int $vaterId): array
    {
        $vg   = $typ === 'buch' ? self::BUCH : self::GARN;
        $gwKg = self::zahl($v['KnGWEDI']);
        $garn = $typ === 'garn' && $gwKg > 0 && $gwKg < 5;

        $text = $v['MARKTTXT60D'] !== '' ? $v['MARKTTXT60D'] : ($kind['MARKTTXT60D'] ?? '');
        if ($typ === 'garn' && $v['PFLEGETXT62D'] !== '') $text .= ($text !== '' ? '<p>' : '') . '<strong>Pflege:</strong> ' . $v['PFLEGETXT62D'] . ($text !== '' ? '</p>' : '');

        return [
            'vaterartikel_id' => $vaterId, 'hat_eigenen_lagerstand' => 1,
            'artikelnummer' => $nr, 'name' => $name,
            'hersteller_id' => $this->hersteller($v['Label'] !== '' ? $v['Label'] : ($kind['Label'] ?? '')),
            'steuerklasse_id' => $vg['steuerklasse_id'], 'artikel_gruppe_id' => $vg['artikel_gruppe_id'], 'artikeltyp_id' => $vg['artikeltyp_id'],
            'kurzbeschreibung' => null, 'beschreibung' => $text !== '' ? $text : null,
            'technische_details' => null, 'beschreibung_intern' => null, 'meta_titel' => null, 'meta_description' => null, 'url_slug' => null,
            'einheit_id' => $vg['einheit_id'],
            'inhalt_menge' => $garn ? round($gwKg * 1000, 3) : null, 'inhalt_einheit' => $garn ? 'g' : null,
            'gewicht_artikel' => $garn ? $gwKg : null, 'gewicht_versand' => null,
            'laenge' => null, 'breite' => null, 'hoehe' => null, 'herkunftsland' => null,
            'taric_code' => self::taric($v['Zollnummer_DE'] !== '' ? $v['Zollnummer_DE'] : $kind['Zollnummer_DE']),
            'grundpreis_bezugsmenge' => $garn ? 100 : 0, 'grundpreis_anzeigen' => $garn ? 1 : 0,
            'charge_pflicht' => $typ === 'garn' ? 1 : 0,
            'ist_auslaufartikel' => 0, 'ueberverkauf_erlaubt' => 0, 'aktiv' => 1,
            'zustand' => 'neu', 'zustand_vater_id' => null,
        ];
    }

    private function setzeMerkmale(int $artikelId, array $v): void
    {
        $set = function (string $slug, string $wert) use ($artikelId) {
            if ($wert !== '' && $this->merkmale->merkmalId($slug)) $this->merkmale->setzeMerkmal($artikelId, $slug, [$wert]);
        };
        $zus = preg_replace('/(\d)\s+%/u', '$1%', $v['Zusammensetzung_D']);
        $set('zusammensetzung', preg_replace('/\s*,\s*/u', ', ', $zus));

        $g = self::zahl($v['KnGWEDI']) * 1000;
        $m = (int) self::zahl($v['LfLg']);
        if ($g > 0 && $g < 5000 && $m > 0) $set('gewicht_laenge', self::de($g) . " g = $m m");

        $nadel = str_replace('.', ',', preg_replace('/\s*[-–]\s*/u', '-', $v['Nadelstaerke']));
        $set('nadelstaerke', $nadel);

        if ((int) $v['Maschen'] > 0 && (int) $v['Reihen'] > 0) {
            $set('maschenprobe', '10 x 10 cm = ' . (int) $v['Maschen'] . ' M x ' . (int) $v['Reihen'] . ' R');
        }
        $this->merkmale->bereicheAbleiten($artikelId);
    }

    // ══════════════════════════════════════════════════════════════════
    //  Hilfen
    // ══════════════════════════════════════════════════════════════════

    private function ladeMaps(): void
    {
        $this->lyMap      = $this->repo->ladeLyArtikel();
        $this->eanMap     = $this->repo->ladeEanMap();
        $this->ausschluss = $this->repo->ladeAusschluss('lang');
    }

    private function farbAchse(): int
    {
        if ($this->farbAchseId === null) {
            $this->farbAchseId = $this->repo->findAchseIdByName('Farbe')
                ?? throw new RuntimeException('Achse „Farbe“ fehlt (Artikel → Achsen).');
        }
        return $this->farbAchseId;
    }

    private array $herstellerCache = [];
    private function hersteller(string $label): ?int
    {
        $key = mb_strtolower($label);
        if (!array_key_exists($key, $this->herstellerCache)) {
            $this->herstellerCache[$key] = ($label !== '' ? $this->repo->findHerstellerIdByName($label) : null)
                ?? $this->repo->findHerstellerIdByName('Lang Yarns');
        }
        return $this->herstellerCache[$key];
    }

    /** EK je Knäuel (Garn: EP/kg × Knäuelgewicht) bzw. je Stück (ohne Gewicht). */
    private function ek(array $kind, ?array $vater): ?float
    {
        $ep = self::zahl($kind['EP/KG']);
        if ($ep <= 0) return null;
        $gw = self::zahl($kind['KnGWEDI'] !== '' ? $kind['KnGWEDI'] : ($vater['KnGWEDI'] ?? ''));
        return round($gw > 0 && $gw < 5 ? $ep * $gw : $ep, 2);
    }

    private function kindName(string $typ, string $vaterName, string $knr, array $k): string
    {
        if ($typ === 'buch') return preg_replace('/^FAM\s+/u', 'Fatto a Mano ', $k['Bez_1']);
        return trim($vaterName . ' ' . self::wertText($knr, $k));
    }

    /** Achsenwert wie bei den vorhandenen Lang-Artikeln: "0003 grau" */
    private static function wertText(string $knr, array $k): string
    {
        $suffix = substr($knr, strpos($knr, '.') + 1);
        $farbe  = $k['Farbe_DE'] !== '' ? $k['Farbe_DE'] : mb_strtolower($k['Farbe']);
        return trim("$suffix $farbe");
    }

    /** Deutsche Fassung? Ohne Sprachkürzel im Namen (z.B. Infoflyer) gilt sie als deutsch. */
    private static function istDeutscheFassung(array $k): bool
    {
        $text = ' ' . $k['Bez_1'] . ' ';
        if (!preg_match('/[\s\/](DE|D|FR|F|NL|EN|DK|IT|RUS|ES)(?=[\s\/])/u', $text)) return true;
        return (bool) preg_match('/[\s\/](DE|D)(?=[\s\/])/u', $text);
    }

    private static function haeufigsterPreis(array $kinder): float
    {
        $z = [];
        foreach ($kinder as $k) if ($k['vk'] > 0) $z[(string) $k['vk']] = ($z[(string) $k['vk']] ?? 0) + 1;
        if (!$z) return 0.0;
        arsort($z);
        return (float) array_key_first($z);
    }

    private static function zahl(string $s): float
    {
        return (float) str_replace(',', '.', trim($s));
    }

    private static function de(float $z): string
    {
        return rtrim(rtrim(number_format($z, 2, ',', ''), '0'), ',');
    }

    private static function ean(string $s): ?string
    {
        return preg_match('/^\d{13}$/', trim($s)) ? trim($s) : null;
    }

    /** Zollnummer DE (11-stellig) -> 8-stelliger KN-Code für Zollinhaltserklärungen */
    private static function taric(string $s): ?string
    {
        $d = preg_replace('/\D/', '', $s);
        return strlen($d) >= 8 ? substr($d, 0, 8) : null;
    }

    private function jarvisId(): int
    {
        return (int) $this->db->query("SELECT id FROM benutzer WHERE username = 'system' LIMIT 1")->fetchColumn();
    }
}
