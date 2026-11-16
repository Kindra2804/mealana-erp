<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/VariantenRepository.php';
require_once __DIR__ . '/VariantenService.php';
require_once __DIR__ . '/../artikel/ArtikelRepository.php';
require_once __DIR__ . '/../artikel/ArtikelService.php';
require_once __DIR__ . '/../lager/LagerService.php';
require_once __DIR__ . '/../shop/ShopSyncRepository.php';

/**
 * Varianten-Muster übernehmen (Jacky 2026-11-16): Ein „Master“-Vater legt fest, in welchen
 * Varianten ein Artikel theoretisch verfügbar ist (z.B. Untersetzer in Kork / Kunstleder
 * schwarz / braun / Schiefer). Das Muster wird auf andere Artikel übertragen:
 *
 *  - Achsen + Werte, die dem Ziel fehlen, werden ergänzt (Abgleich über Achse + Wert-Text,
 *    weil Werte je Artikel eigene Zeilen sind). Es wird nie etwas gelöscht.
 *  - Je Master-Kind: hat das Ziel diese Kombination schon → überspringen (inkl. Bestand),
 *    sonst neues Kind. Nummer/Name = Ziel + der Teil, den das Master-Kind hinten dran hat
 *    (ID-U-K20-KLS → ID-U-A49-KLS). Ist die Nummer schon von einem anderen Artikel belegt → Konflikt, übersprungen.
 *  - Das neue Kind erbt alles vom eigenen Vater (wie der Generator). Preis: hat die Achse beim Ziel
 *    einen Aufpreis/Direktpreis → wie der Generator; sonst Unterschied Master-Kind ↔ Master-Vater
 *    (Schiefer +3 €). Abweichende Maße/Gewichte des Master-Kinds kommen immer mit.
 *  - Neue Kinder sind aktiv (Babsi soll sie in der Standardansicht sehen) und in den Shops
 *    des Ziel-Vaters zugeordnet.
 *  - Ziel mit eigenem Bestand, das jetzt Varianten bekommt → Bestand an eine gewählte Variante.
 *
 * Genutzt von: Artikelliste → Massenaktion „Varianten-Muster übernehmen“ und Artikel kopieren
 * („Varianten mitkopieren“).
 */
class VariantenMusterService
{
    private const MASSE = ['gewicht_artikel', 'gewicht_versand', 'laenge', 'breite', 'hoehe'];

    private VariantenRepository $repo;
    private ArtikelRepository $artikelRepo;

    public function __construct()
    {
        $this->repo        = new VariantenRepository();
        $this->artikelRepo = new ArtikelRepository();
    }

    /**
     * Probelauf für mehrere Ziele. Ändert nichts.
     * @return array{master: array, ziele: array}
     */
    public function vorschau(int $masterId, array $zielIds): array
    {
        $master = $this->ladeMaster($masterId);
        $ziele = [];
        foreach (array_unique(array_map('intval', $zielIds)) as $zid) {
            if ($zid <= 0 || $zid === $masterId) continue;
            $ziele[] = $this->plane($master, $zid);
        }
        return ['master' => [
            'id' => $master['artikel']['id'], 'artikelnummer' => $master['artikel']['artikelnummer'],
            'name' => $master['artikel']['name'], 'kinder' => count($master['kinder']),
        ], 'ziele' => $ziele];
    }

    /**
     * Führt die Übernahme für ein Ziel aus (eigene Transaktion).
     * $bestandWahl: 'm:<masterKindId>' (wird neu angelegt) oder 'k:<zielKindId>' (existiert) —
     * Pflicht, wenn das Ziel eigenen Bestand hat und danach Varianten besitzt.
     */
    public function uebernehmen(int $masterId, int $zielId, ?string $bestandWahl = null, ?int $benutzerId = null): array
    {
        $master = $this->ladeMaster($masterId);
        $plan   = $this->plane($master, $zielId);
        if ($plan['fehler']) {
            return ['erfolg' => false, 'fehler' => $plan['fehler']];
        }
        $neu = array_values(array_filter($plan['zeilen'], fn($z) => $z['status'] === 'neu'));
        if (!$neu && !$plan['achsen_neu'] && !$plan['werte_neu']) {
            return ['erfolg' => true, 'neu' => 0, 'hinweis' => 'Alles schon vorhanden'];
        }
        if ($plan['bestand_text'] !== '' && $neu) {
            if (!$bestandWahl || !isset($plan['bestand_optionen'][$bestandWahl])) {
                return ['erfolg' => false, 'fehler' => $plan['ziel']['artikelnummer'] . ' hat eigenen Bestand (' . $plan['bestand_text'] . ') — bitte wählen, welche Variante ihn übernimmt'];
            }
        }

        $db = Database::getInstance();
        $eigeneTransaktion = !$db->inTransaction();
        if ($eigeneTransaktion) $db->beginTransaction();
        try {
            $wertMap  = $this->ergaenzeAchsenUndWerte($master, $zielId);   // master_wert_id → ziel_wert_id
            $zielVater = $this->artikelRepo->findById($zielId);

            // Kinder anlegen über den bestehenden Generator-Weg (erbt ~25 Felder vom Ziel-Vater)
            $kombis = [];
            foreach ($neu as $z) {
                $ids = array_map(fn($mw) => $wertMap[$mw], $z['master_wert_ids']);
                sort($ids);
                $kombis[] = ['key' => implode(',', $ids), 'artikelnummer' => $z['nr'], 'name' => $z['name'], 'ean' => ''];
            }
            $neueIds = [];
            $achsenPreis = [];
            if ($kombis) {
                $erg = (new VariantenService())->erstelleKombinationen($zielVater, true, $kombis);
                $neueIds = $erg['ids'];
                // Preis aus der Achse (Aufpreis/Direktpreis an artikel_achsen des Ziels): wie der Generator.
                // Nur Varianten OHNE Achsenpreis bekommen unten den Unterschied vom Master-Kind
                // (Preis direkt an der Variante gepflegt, z.B. Schiefer 9 € statt 6 €). Jacky 2026-11-16: beide Fälle.
                $achsenPreis = $erg['preisAnpassungen'] ?? [];
                (new ArtikelService())->kopiereVaterRelationenZuKindern($zielId, $neueIds, $achsenPreis);
            }

            $masterVaterPreise = $this->repo->findBasisPreise($master['artikel']['id']);
            $zielVaterPreise   = $this->repo->findBasisPreise($zielId);
            $masterVaterMasse  = $this->repo->findMasse($master['artikel']['id']);
            $shopIds           = $this->repo->findAktiveShopIds($zielId);
            $syncRepo          = new ShopSyncRepository();
            $neuJeMasterKind   = [];

            foreach ($neu as $i => $z) {
                $kindId = (int)($neueIds[$i] ?? 0);
                if (!$kindId) continue;
                $neuJeMasterKind[$z['master_kind_id']] = $kindId;

                // Preis: Ziel-Vater + (Master-Kind − Master-Vater), je Kundengruppe — außer der Preis kommt aus der Achse
                foreach (isset($achsenPreis[$kindId]) ? [] : $this->repo->findBasisPreise($z['master_kind_id']) as $kg => $mk) {
                    $basis = $zielVaterPreise[$kg] ?? null;
                    $mv    = $masterVaterPreise[$kg] ?? null;
                    if ($basis === null) {
                        $this->repo->setzeBasisPreis($kindId, $kg, $mk['brutto'], $mk['netto']);
                    } elseif ($mv !== null) {
                        $this->repo->setzeBasisPreis($kindId, $kg,
                            round($basis['brutto'] + $mk['brutto'] - $mv['brutto'], 2),
                            round($basis['netto'] + $mk['netto'] - $mv['netto'], 2));
                    } else {
                        $this->repo->setzeBasisPreis($kindId, $kg, $mk['brutto'], $mk['netto']);
                    }
                }

                // Maße/Gewichte, in denen sich das Master-Kind vom Master-Vater unterscheidet
                $mkMasse = $this->repo->findMasse($z['master_kind_id']);
                $abweichend = [];
                foreach (self::MASSE as $f) {
                    if (($mkMasse[$f] ?? null) !== null && (string)$mkMasse[$f] !== (string)($masterVaterMasse[$f] ?? '')) {
                        $abweichend[$f] = $mkMasse[$f];
                    }
                }
                $this->repo->setzeMasse($kindId, $abweichend);

                foreach ($shopIds as $sid) {
                    $syncRepo->upsertZuweisung($kindId, $sid, true);
                }
            }

            // Eigenen Bestand des Ziels an die gewählte Variante
            $bestandInfo = null;
            if ($plan['bestand_text'] !== '' && $bestandWahl) {
                $kindId = str_starts_with($bestandWahl, 'm:')
                    ? ($neuJeMasterKind[(int)substr($bestandWahl, 2)] ?? 0)
                    : (int)substr($bestandWahl, 2);
                if ($kindId) {
                    $ue = (new LagerService())->bestandAufVarianteUebertragen($zielId, $kindId, $benutzerId);
                    if (!$ue['erfolg']) throw new RuntimeException($ue['fehler']);
                    $bestandInfo = $ue;
                }
            }

            if ($eigeneTransaktion) $db->commit();
        } catch (Throwable $e) {
            if ($eigeneTransaktion && $db->inTransaction()) $db->rollBack();
            return ['erfolg' => false, 'fehler' => $plan['ziel']['artikelnummer'] . ': ' . $e->getMessage()];
        }

        Logger::log('varianten.muster_uebernommen', 'artikel', $zielId, [
            'master' => $master['artikel']['artikelnummer'],
            'neu'    => array_column($neu, 'nr'),
            'achsen_neu' => $plan['achsen_neu'],
            'werte_neu'  => $plan['werte_neu'],
            'bestand_an' => $bestandInfo['kind_nr'] ?? null,
        ], $benutzerId ?? ($_SESSION['benutzer']['id'] ?? $this->jarvisId()));

        return ['erfolg' => true, 'neu' => count($neueIds), 'nummern' => array_column($neu, 'nr'),
                'bestand' => $bestandInfo ? ['menge' => $bestandInfo['menge'], 'kind_nr' => $bestandInfo['kind_nr']] : null];
    }

    // ── intern ──────────────────────────────────────────────────────────────

    private function ladeMaster(int $masterId): array
    {
        $artikel = $this->artikelRepo->findById($masterId);
        if (!$artikel) throw new RuntimeException('Master-Artikel nicht gefunden');
        if (!empty($artikel['vaterartikel_id'])) throw new RuntimeException('Der Master muss ein Vater-Artikel sein, keine Variante');
        // nur aktive Varianten bilden das Muster (ausgelaufene nicht weitergeben)
        $kinder = array_filter($this->kinderMitWerten($masterId), fn($k) => $k['aktiv']);
        if (!$kinder) throw new RuntimeException($artikel['artikelnummer'] . ' hat keine aktiven Varianten — als Master ungeeignet');
        return [
            'artikel' => $artikel,
            'kinder'  => $kinder,
            'achsen'  => $this->repo->findAchsenByArtikelId($masterId),
            'werte'   => $this->repo->findWerteRoh($masterId),
        ];
    }

    /** [kind_id => [id, artikelnummer, name, werte: [achse_id => [wert_id, wert]]]] */
    private function kinderMitWerten(int $vaterId): array
    {
        $kinder = [];
        foreach ($this->repo->findKinderMitWerten($vaterId) as $r) {
            $id = (int)$r['kind_id'];
            $kinder[$id] ??= ['id' => $id, 'artikelnummer' => $r['artikelnummer'], 'name' => $r['name'], 'aktiv' => (bool)$r['aktiv'], 'werte' => []];
            $kinder[$id]['werte'][(int)$r['achse_id']] = ['wert_id' => (int)$r['wert_id'], 'wert' => $r['wert']];
        }
        return $kinder;
    }

    private static function norm(string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
    }

    /** Schlüssel einer Kombination über Achse + Wert-Text, unabhängig von Wert-IDs */
    private static function kombiSchluessel(array $werte): string
    {
        $teile = [];
        foreach ($werte as $achseId => $w) $teile[] = $achseId . '=' . self::norm($w['wert']);
        sort($teile);
        return implode('|', $teile);
    }

    private function plane(array $master, int $zielId): array
    {
        $ziel = $this->artikelRepo->findById($zielId);
        $plan = [
            'ziel' => ['id' => $zielId, 'artikelnummer' => $ziel['artikelnummer'] ?? '?', 'name' => $ziel['name'] ?? ''],
            'fehler' => null, 'achsen_neu' => [], 'werte_neu' => [], 'zeilen' => [],
            'bestand_text' => '', 'bestand_optionen' => [],
        ];
        if (!$ziel)                              { $plan['fehler'] = 'Artikel nicht gefunden'; return $plan; }
        if (!empty($ziel['vaterartikel_id']))    { $plan['fehler'] = 'ist selbst eine Variante — bitte den Vater-Artikel wählen'; return $plan; }
        if (!empty($ziel['ist_konfigurierbar'])) { $plan['fehler'] = 'ist konfigurierbar (keine vorab angelegten Varianten)'; return $plan; }

        $masterAchsenIds = array_map(fn($a) => (int)$a['achse_id'], $master['achsen']);
        $zielAchsen      = $this->repo->findAchsenByArtikelId($zielId);
        $zielAchsenIds   = array_map(fn($a) => (int)$a['achse_id'], $zielAchsen);

        $fremd = array_diff($zielAchsenIds, $masterAchsenIds);
        if ($fremd) {
            $namen = array_map(fn($a) => $a['name'], array_filter($zielAchsen, fn($a) => in_array((int)$a['achse_id'], $fremd, true)));
            $plan['fehler'] = 'hat zusätzliche Achse(n) ' . implode(', ', $namen) . ', die der Master nicht hat — Muster passt nicht';
            return $plan;
        }
        foreach ($master['achsen'] as $a) {
            if (!in_array((int)$a['achse_id'], $zielAchsenIds, true)) $plan['achsen_neu'][] = $a['name'];
        }

        // fehlende Werte (Achse + Text)
        $zielWerte = [];
        foreach ($this->repo->findWerteRoh($zielId) as $w) $zielWerte[(int)$w['achse_id'] . '=' . self::norm($w['wert'])] = true;
        $achsName = array_column($master['achsen'], 'name', 'achse_id');
        foreach ($master['werte'] as $w) {
            if (!isset($zielWerte[(int)$w['achse_id'] . '=' . self::norm($w['wert'])])) {
                $plan['werte_neu'][] = ($achsName[$w['achse_id']] ?? '') . ': ' . $w['wert'];
            }
        }

        // Kombinationen
        $zielKinder = [];
        foreach ($this->kinderMitWerten($zielId) as $k) $zielKinder[self::kombiSchluessel($k['werte'])] = $k;

        $mNr   = $master['artikel']['artikelnummer'];
        $mName = $master['artikel']['name'];
        foreach ($master['kinder'] as $mk) {
            $werteText = implode(' / ', array_column($mk['werte'], 'wert'));
            $nrTeil   = str_starts_with($mk['artikelnummer'], $mNr) ? substr($mk['artikelnummer'], strlen($mNr))
                      : '-' . implode('-', array_column($mk['werte'], 'wert'));
            $nameTeil = str_starts_with($mk['name'], $mName) ? substr($mk['name'], strlen($mName))
                      : ' ' . $werteText;
            $zeile = [
                'master_kind_id'  => $mk['id'],
                'master_nr'       => $mk['artikelnummer'],
                'master_wert_ids' => array_column($mk['werte'], 'wert_id'),
                'werte'           => $werteText,
                'nr'              => $ziel['artikelnummer'] . $nrTeil,
                'name'            => $ziel['name'] . $nameTeil,
                'status'          => 'neu',
                'vorhanden_id'    => null,
                'vorhanden_nr'    => null,
            ];
            $vorhanden = $zielKinder[self::kombiSchluessel($mk['werte'])] ?? null;
            if ($vorhanden) {
                $zeile['status'] = 'vorhanden';
                $zeile['vorhanden_id'] = $vorhanden['id'];
                $zeile['vorhanden_nr'] = $vorhanden['artikelnummer'];
            } elseif ($this->repo->findIdByArtikelnummer($zeile['nr']) !== null) {
                $zeile['status'] = 'konflikt';   // Nummer gehört einem anderen Artikel
            }
            $plan['zeilen'][] = $zeile;
        }
        if (count(array_unique(array_column(array_filter($plan['zeilen'], fn($z) => $z['status'] === 'neu'), 'nr')))
            < count(array_filter($plan['zeilen'], fn($z) => $z['status'] === 'neu'))) {
            $plan['fehler'] = 'Das Muster ergibt doppelte Artikelnummern (Kürzel der Master-Varianten prüfen)';
            return $plan;
        }

        // Eigener Bestand → Wahl, welche Variante übernimmt
        $bestand = (new LagerService())->eigenerBestandZeilen($zielId);
        if ($bestand) {
            $plan['bestand_text'] = implode(', ', array_map(
                fn($z) => $z['lager_name'] . ' ' . rtrim(rtrim(number_format((float)$z['bestand'], 3, ',', ''), '0'), ',')
                          . ($z['charge'] !== null ? ' (Charge ' . $z['charge'] . ')' : ''),
                $bestand));
            foreach ($plan['zeilen'] as $z) {
                if ($z['status'] === 'neu')       $plan['bestand_optionen']['m:' . $z['master_kind_id']] = $z['nr'] . ' (neu)';
                if ($z['status'] === 'vorhanden') $plan['bestand_optionen']['k:' . $z['vorhanden_id']]   = $z['vorhanden_nr'];
            }
            foreach ($zielKinder as $k) $plan['bestand_optionen']['k:' . $k['id']] ??= $k['artikelnummer'];
        }
        return $plan;
    }

    /**
     * Ergänzt fehlende Achsen + Werte beim Ziel. Gibt die Zuordnung Master-Wert-ID → Ziel-Wert-ID zurück.
     */
    private function ergaenzeAchsenUndWerte(array $master, int $zielId): array
    {
        $zielAchsen = array_column($this->repo->findAchsenByArtikelId($zielId), null, 'achse_id');
        foreach ($master['achsen'] as $a) {
            if (isset($zielAchsen[$a['achse_id']])) continue;
            $this->repo->insertArtikelAchse([
                'artikel_id' => $zielId, 'achse_id' => (int)$a['achse_id'], 'sort_order' => (int)$a['sort_order'],
            ]);
            $this->repo->updateAchsePreis($zielId, (int)$a['achse_id'], $a['preis_modus'], (float)$a['preis_wert']);
        }

        $zielWerte = [];
        foreach ($this->repo->findWerteRoh($zielId) as $w) $zielWerte[(int)$w['achse_id'] . '=' . self::norm($w['wert'])] = (int)$w['id'];

        $map = [];
        $neuAngelegt = [];   // master_wert_id → ziel_wert_id (für Bedingungen)
        foreach ($master['werte'] as $w) {
            $k = (int)$w['achse_id'] . '=' . self::norm($w['wert']);
            if (!isset($zielWerte[$k])) {
                $zielWerte[$k] = $this->repo->insertWert([
                    'artikel_id' => $zielId, 'achse_id' => (int)$w['achse_id'], 'wert' => $w['wert'],
                    'wert_zusatz' => $w['wert_zusatz'], 'aufpreis' => (float)$w['aufpreis'], 'sort_order' => (int)$w['sort_order'],
                ]);
                if ($w['rohmaterial_artikel_id']) $this->repo->setzeWertRohmaterial($zielWerte[$k], (int)$w['rohmaterial_artikel_id']);
                $neuAngelegt[(int)$w['id']] = $zielWerte[$k];
            }
            $map[(int)$w['id']] = $zielWerte[$k];
        }
        // Bedingungen neu angelegter Werte auf die Ziel-Werte umschlüsseln
        foreach ($master['werte'] as $w) {
            if (isset($neuAngelegt[(int)$w['id']]) && $w['bedingungs_wert_id'] && isset($map[(int)$w['bedingungs_wert_id']])) {
                $this->repo->setzeWertBedingung($neuAngelegt[(int)$w['id']], $map[(int)$w['bedingungs_wert_id']]);
            }
        }
        return $map;
    }

    private function jarvisId(): int
    {
        return (int) Database::getInstance()->query("SELECT id FROM benutzer WHERE username = 'system'")->fetchColumn();
    }
}
