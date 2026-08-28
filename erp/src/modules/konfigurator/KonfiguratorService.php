<?php

require_once __DIR__ . '/KonfiguratorRepository.php';
require_once __DIR__ . '/../varianten/VariantenRepository.php';
require_once __DIR__ . '/../varianten/VariantenService.php';
require_once __DIR__ . '/../achsen/AchsenRepository.php';
require_once __DIR__ . '/../preise/PreisService.php';
require_once __DIR__ . '/../shop/ShopSyncRepository.php';
require_once __DIR__ . '/../../core/database.php';

/**
 * KonfiguratorService – Laufzeit-Logik für konfigurierbare Artikel (Schilder & Co.)
 *
 * Eigenständiges Modul (kein Teil von VariantenService): VariantenService ist Schreib-/
 * Einmal-Generierungslogik für die klassische Vater/Kind-Kombinatorik. Der Konfigurator ist
 * reine Laufzeit-Leselogik mit mehreren Konsumenten (Kasse, Shop-Sync-Preis-Matrix,
 * Bestellimport) und soll laut Projektplanung später ein eigenständig lizenzierbares Modul
 * werden — beides spricht gegen eine Vermischung mit VariantenService.
 *
 * WICHTIG zur Preisberechnung (siehe berechnePreis()): additiv, NICHT identisch mit
 * VariantenService::erstelleKombinationen() (dort wird varianten_achse_werte.aufpreis
 * bewusst NICHT summiert — das ist die Einmal-Generierungslogik für Kind-Artikel und wird
 * hier absichtlich nicht angefasst, um bestehende Kind-Preise nicht rückwirkend zu ändern).
 *
 * @see VariantenService::erstelleKombinationen() für die abweichende Vater/Kind-Preisregel
 */
class KonfiguratorService
{
    private KonfiguratorRepository $repo;
    private VariantenRepository $variantenRepo;
    private VariantenService $variantenService;
    private AchsenRepository $achsenRepo;
    private PreisService $preisService;
    private PDO $db;

    public function __construct()
    {
        $this->repo             = new KonfiguratorRepository();
        $this->variantenRepo    = new VariantenRepository();
        $this->variantenService = new VariantenService();
        $this->achsenRepo       = new AchsenRepository();
        $this->preisService     = new PreisService();
        $this->db                = Database::getInstance();
    }

    /** Standard-Kundengruppen-ID: cached per Request via static, Fallback auf 1 (Muster: ArtikelRepository). */
    private function findStandardKgId(): int
    {
        static $id = null;
        if ($id === null) {
            $id = (int) $this->db->query("SELECT id FROM kundengruppen WHERE ist_standard = 1 LIMIT 1")->fetchColumn();
        }
        return $id ?: 1;
    }

    /**
     * Achsen (als Dimensionen, inkl. bedingter Anzeige) + Werte + Aufpreise eines konfigurierbaren
     * Artikels — Basis für die Kasse-Auswahl-UI und die spätere Shop-Preis-Matrix.
     *
     * Nutzt bewusst VariantenService::baueAchsenDimensionen() statt roh auf artikel_achsen zu
     * arbeiten, sonst würde die Kasse Sub-Achsen (z.B. "Mix"/"Uni" unter "Farbe") anders
     * gruppieren als der Shop-Sync — exakt der Bug-Typ vom 2026-07-29 (siehe dortiger Docblock).
     *
     * Bedingung/Achsen-Aufpreis werden aus artikel_achsen nachträglich pro Dimension angehängt,
     * da baueAchsenDimensionen() nur name/achse_id/werte liefert. Bei unionierten Sub-Achsen
     * gilt die Bedingung der repräsentativen (Eltern-)Achse der Dimension — eine abweichende
     * Bedingung auf einer einzelnen Sub-Achse wird hier bewusst nicht gesondert behandelt.
     */
    public function getKonfiguration(int $artikelId): array
    {
        $achsen = $this->variantenRepo->findAchsenByArtikelId($artikelId);
        $werte  = $this->variantenRepo->findWerteByArtikelId($artikelId);

        // Nur Darstellungsformen, die auch als Shop-Attribut sinnvoll sind (Muster: ShopSyncRepository)
        $achsen = array_values(array_filter(
            $achsen,
            fn($a) => in_array($a['darstellungsform'], ['swatches', 'dropdown', 'radiobutton'], true)
        ));

        $achseNamenById = array_column($this->achsenRepo->findAll(), 'name', 'id');
        $dimensionen    = $this->variantenService->baueAchsenDimensionen($achsen, $werte, $achseNamenById);

        $achsenById = [];
        foreach ($achsen as $a) {
            $achsenById[(int)$a['achse_id']] = $a;
        }

        $ergebnis = [];
        foreach ($dimensionen as $dim) {
            $achseId = (int)$dim['achse_id'];
            $achse   = $achsenById[$achseId] ?? null;
            $modus   = $achse['preis_modus'] ?? 'aufpreis';

            $ergebnis[] = [
                'achse_id'        => $achseId,
                'name'            => $dim['name'],
                'bedingung'       => (!empty($achse['bedingungs_achse_id']) && !empty($achse['bedingungs_wert_id']))
                                        ? ['achse_id' => (int)$achse['bedingungs_achse_id'], 'wert_id' => (int)$achse['bedingungs_wert_id']]
                                        : null,
                'achsen_aufpreis' => $modus === 'aufpreis' ? (float)($achse['preis_wert'] ?? 0) : 0.0,
                'werte'           => array_map(fn($w) => [
                    'wert_id'       => (int)$w['id'],
                    'wert'          => $w['wert'],
                    'wert_zusatz'   => $w['wert_zusatz'] ?? null,
                    'wert_aufpreis' => (float)($w['aufpreis'] ?? 0),
                ], $dim['werte']),
            ];
        }

        return $ergebnis;
    }

    /**
     * Additive Preisberechnung: Basispreis (PreisService::getEffektiverPreis(), oder der
     * Direktpreis einer gewählten Achse im direktpreis-Modus) + Σ (Achsen-Aufpreis + Wert-Aufpreis)
     * der gewählten Werte. $wertIds = eine gewählte Wert-ID pro Achse.
     *
     * Validiert hart, dass jede wert_id wirklich zu $artikelId gehört (Security-Gate gegen
     * manipulierte Client-IDs) und dass Bedingungs-Achsen nur mitgerechnet werden, wenn ihr
     * Bedingungswert selbst mit ausgewählt wurde.
     */
    public function berechnePreis(int $artikelId, array $wertIds, ?int $kgId = null): array
    {
        $artikel = $this->repo->findArtikelBasisdaten($artikelId);
        if (!$artikel) {
            return ['erfolg' => false, 'fehler' => ['Artikel nicht gefunden']];
        }
        if (!$artikel['ist_konfigurierbar']) {
            return ['erfolg' => false, 'fehler' => ['Artikel ist nicht konfigurierbar']];
        }
        if (!empty($artikel['ist_vater'])) {
            return ['erfolg' => false, 'fehler' => ['Artikel hat bereits Vater/Kind-Varianten — Konfigurator-Preisberechnung nicht zulässig']];
        }

        $kgId ??= $this->findStandardKgId();
        $basisErgebnis = $this->preisService->getEffektiverPreis($artikelId, $kgId);
        if ($basisErgebnis['brutto_vk'] === null) {
            return ['erfolg' => false, 'fehler' => ['Kein Preis hinterlegt']];
        }
        $basisBrutto = (float)$basisErgebnis['brutto_vk'];

        $wertIds = array_values(array_unique(array_map('intval', $wertIds)));

        $werteById = [];
        if (!empty($wertIds)) {
            foreach ($this->variantenRepo->findWerteByIds($wertIds) as $w) {
                if ((int)$w['artikel_id'] !== $artikelId) {
                    return ['erfolg' => false, 'fehler' => ['Ungültige Auswahl (Wert gehört nicht zu diesem Artikel)']];
                }
                $werteById[(int)$w['id']] = $w;
            }
        }
        foreach ($wertIds as $wid) {
            if (!isset($werteById[$wid])) {
                return ['erfolg' => false, 'fehler' => ['Ungültige Auswahl (unbekannter Wert)']];
            }
        }

        $achsenById = [];
        foreach ($this->variantenRepo->findAchsenByArtikelId($artikelId) as $a) {
            $achsenById[(int)$a['achse_id']] = $a;
        }
        $achsenPreisMap = $this->variantenRepo->findAchsenPreisMap($artikelId);

        $direktpreis       = null;
        $aufpreisSumme     = 0.0;
        $positionen        = [];

        foreach ($wertIds as $wid) {
            $wert    = $werteById[$wid];
            $achseId = (int)$wert['achse_id'];
            $achse   = $achsenById[$achseId] ?? null;
            $achseName = $achse['name'] ?? '';

            $bedAchseId = (int)($achse['bedingungs_achse_id'] ?? 0);
            $bedWertId  = (int)($achse['bedingungs_wert_id'] ?? 0);
            if ($bedAchseId && $bedWertId && !in_array($bedWertId, $wertIds, true)) {
                return ['erfolg' => false, 'fehler' => ["Achse „{$achseName}“ ist nur gültig, wenn die zugehörige Bedingung erfüllt ist"]];
            }

            $achsenAufpreis = 0.0;
            if (isset($achsenPreisMap[$achseId])) {
                $modus     = $achsenPreisMap[$achseId]['modus'];
                $preisWert = (float)$achsenPreisMap[$achseId]['preis_wert'];
                if ($modus === 'direktpreis') {
                    $direktpreis = $preisWert;
                } else {
                    $achsenAufpreis = $preisWert;
                }
            }
            $wertAufpreis = (float)($wert['aufpreis'] ?? 0);

            $aufpreisSumme += $achsenAufpreis + $wertAufpreis;

            $positionen[] = [
                'achse_id'        => $achseId,
                'achse_name'      => $achseName,
                'wert_id'         => $wid,
                'wert'            => $wert['wert'],
                'achsen_aufpreis' => $achsenAufpreis,
                'wert_aufpreis'   => $wertAufpreis,
            ];
        }

        $brutto = round(($direktpreis ?? $basisBrutto) + $aufpreisSumme, 2);
        $satz   = (float)($artikel['steuer_prozent'] ?? 0);
        $netto  = round($brutto / (1 + $satz / 100), 4);

        return [
            'erfolg'          => true,
            'fehler'          => [],
            'artikel_id'      => $artikelId,
            'basis_brutto'    => $basisBrutto,
            'aufpreis_brutto' => round($aufpreisSumme, 2),
            'brutto'          => $brutto,
            'netto'           => $netto,
            'steuer_prozent'  => $satz,
            'positionen'      => $positionen,
            'beschreibung'    => $this->baueBeschreibung($positionen),
        ];
    }

    /**
     * Preis-Matrix + Achsen-Struktur als reines PHP-Array für die WooCommerce-`meta_data`-JSON
     * (siehe ShopSyncService::baueKonfiguratorFelder()). `gesamt_aufpreis` wird HIER
     * vorberechnet (Achsen-Aufpreis + Wert-Aufpreis) -- der Vertrag mit dem künftigen
     * Shop-Frontend bleibt dadurch trivial: Preis = basis_brutto + Σ gesamt_aufpreis der
     * gewählten Werte, keine zweite Kopie der additiven Rechenregel im JS nötig.
     *
     * `term_id`/`attribut_id` kommen aus den bestehenden Shop-Sync-Zuweisungstabellen
     * (varianten_achsen_shops/varianten_achse_werte_shops) -- syncAchsenFuerVater() muss für
     * diesen Artikel bereits gelaufen sein (garantiert, da ShopSyncService das immer vor dem
     * Aufruf dieser Methode tut), sonst bleiben sie defensiv null statt eine Exception zu werfen.
     */
    public function bauePreisMatrix(int $artikelId, int $shopId, ?int $kgId = null): array
    {
        $shopSyncRepo = new ShopSyncRepository();
        $artikel      = $this->repo->findArtikelBasisdaten($artikelId);
        $kgId       ??= $this->findStandardKgId();
        $basis        = $this->preisService->getEffektiverPreis($artikelId, $kgId);

        $achsen = [];
        foreach ($this->getKonfiguration($artikelId) as $achse) {
            $achseZuweisung = $shopSyncRepo->findAchseShopZuweisung($achse['achse_id'], $shopId);

            $werte = [];
            foreach ($achse['werte'] as $wert) {
                $wertZuweisung = $shopSyncRepo->findWertShopZuweisung($wert['wert_id'], $shopId);
                $werte[] = [
                    'wert_id'         => $wert['wert_id'],
                    'label'           => $wert['wert'],
                    'term_id'         => $wertZuweisung ? (int)$wertZuweisung['externe_term_id'] : null,
                    'wert_aufpreis'   => $wert['wert_aufpreis'],
                    'gesamt_aufpreis' => round($achse['achsen_aufpreis'] + $wert['wert_aufpreis'], 2),
                ];
            }

            $achsen[] = [
                'achse_id'        => $achse['achse_id'],
                'attribut_id'     => $achseZuweisung ? (int)$achseZuweisung['externe_attribut_id'] : null,
                'name'            => $achse['name'],
                'bedingung'       => $achse['bedingung'],
                'achsen_aufpreis' => $achse['achsen_aufpreis'],
                'werte'           => $werte,
            ];
        }

        return [
            'version'        => 1,
            'artikel_id'     => $artikelId,
            'sku'            => $artikel['artikelnummer'] ?? '',
            'steuer_prozent' => (float)($artikel['steuer_prozent'] ?? 0),
            'basis_brutto'   => (float)($basis['brutto_vk'] ?? 0),
            'achsen'         => $achsen,
        ];
    }

    /** Speichert die gewählten Werte an eine Position (polymorph, Muster: reservierungen). */
    public function speichereAuswahl(string $referenzTabelle, int $referenzId, array $wertIds): void
    {
        $wertIds = array_values(array_unique(array_map('intval', $wertIds)));
        if (empty($wertIds)) return;

        foreach ($this->variantenRepo->findWerteByIds($wertIds) as $w) {
            $this->repo->insertAuswahl($referenzTabelle, $referenzId, (int)$w['achse_id'], (int)$w['id'], $w['wert']);
        }
    }

    public function ladeAuswahl(string $referenzTabelle, int $referenzId): array
    {
        return $this->repo->findAuswahl($referenzTabelle, $referenzId);
    }

    public function loescheAuswahl(string $referenzTabelle, int $referenzId): void
    {
        $this->repo->deleteAuswahl($referenzTabelle, $referenzId);
    }

    /** Klartext für bezeichnung/Bon: "Durchmesser: 38 cm · Grundfarbe: Rot". Nimmt Zeilen mit achse_name/wert-Keys. */
    public function baueBeschreibung(array $positionen): string
    {
        $teile = array_map(
            fn($p) => ($p['achse_name'] ?? ('Achse ' . $p['achse_id'])) . ': ' . ($p['wert'] ?? ('Wert ' . $p['wert_id'])),
            $positionen
        );
        return implode(' · ', $teile);
    }
}
