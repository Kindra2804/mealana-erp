<?php

require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/KonfiguratorService.php';
require_once __DIR__ . '/KonfigBausteineService.php';
require_once __DIR__ . '/../artikel/ArtikelRepository.php';
require_once __DIR__ . '/../lager/LagerService.php';
require_once __DIR__ . '/../shop/ShopSyncRepository.php';

/**
 * Fertige Schilder (Migration 232, Jacky 2026-11-11): Ein vorproduziertes Schild wird am Layout
 * "durchkonfiguriert" und als eigener Lagerartikel angelegt bzw. eingebucht.
 *
 * - Artikelnummer = Layout-Nummer + Kürzel der gewählten Werte in Achsen-Reihenfolge (Schild-Bausteine),
 *   z.B. ID-W01-20-2F-RT-WS-FI-RT-GL. Ein Wert ohne Kürzel fällt weg -- aber nur, wenn er in seiner Achse
 *   der einzige ohne Kürzel ist (sonst ergäben verschiedene Kombinationen dieselbe Nummer).
 * - Gleiche Kombination = gleiche Nummer = derselbe Artikel: es wird nur eingebucht (und reaktiviert).
 * - Neuer Artikel: Stammdaten vom Layout (Hersteller, Gruppe, Steuer, Typ, Einheit, Maße), Preis =
 *   Konfigurator-Preis, Kategorie = Einstellung "konfig_fertig_kategorie_id", Shop-Kanäle wie das Layout.
 */
class FertigesSchildService
{
    private PDO $db;
    private KonfiguratorService $konfig;
    private KonfigBausteineService $bausteine;
    private ArtikelRepository $artikelRepo;

    public function __construct()
    {
        $this->db          = Database::getInstance();
        $this->konfig      = new KonfiguratorService();
        $this->bausteine   = new KonfigBausteineService();
        $this->artikelRepo = new ArtikelRepository();
    }

    public function zielKategorieId(): int
    {
        return (int) $this->db->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'konfig_fertig_kategorie_id'")->fetchColumn();
    }

    /** Fertige Schilder eines Layouts mit Bestand (für die Übersicht auf der Seite). */
    public function vorhandene(int $layoutId): array
    {
        $st = $this->db->prepare("
            SELECT a.id, a.artikelnummer, a.name, a.aktiv,
                   COALESCE((SELECT SUM(lb.bestand) FROM lagerbestand lb JOIN lager l ON l.id = lb.lager_id AND l.lager_beziehung = 'eigen'
                             WHERE lb.artikel_id = a.id), 0) AS bestand
            FROM konfig_fertige_artikel f JOIN artikel a ON a.id = f.artikel_id
            WHERE f.layout_artikel_id = ? ORDER BY a.artikelnummer");
        $st->execute([$layoutId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Rechnet Artikelnummer, Name, Preis aus, ohne etwas zu ändern.
     * $wertIds = [achse_id => wert_id] (nur sichtbare Achsen). Rückgabe mit 'fehler' => [] wenn ok.
     */
    public function vorschau(int $layoutId, array $wertIds): array
    {
        $layout = $this->artikelRepo->findById($layoutId);
        if (!$layout || empty($layout['ist_konfigurierbar'])) return ['fehler' => ['Das ist kein konfigurierbarer Artikel.']];

        $konfiguration = $this->konfig->getKonfiguration($layoutId);
        $kuerzel = $this->bausteine->kuerzelJeWert($layoutId);
        $gewaehlt = array_map('intval', array_filter($wertIds));
        $fehler = [];
        $teile = [];
        $namen = [];
        $werteJson = [];
        $sichtbar = [];
        foreach ($konfiguration as $achse) {
            $aid = (int) $achse['achse_id'];
            if ($achse['bedingung'] && (int) ($gewaehlt[$achse['bedingung']['achse_id']] ?? 0) !== (int) $achse['bedingung']['wert_id']) continue;
            $sichtbar[$aid] = true;
            $wid = (int) ($gewaehlt[$aid] ?? 0);
            if (!$wid) { $fehler[] = "Bitte „{$achse['name']}“ wählen."; continue; }
            $wert = null;
            foreach ($achse['werte'] as $w) if ((int) $w['wert_id'] === $wid) $wert = $w;
            if (!$wert) { $fehler[] = "Ungültige Auswahl bei „{$achse['name']}“."; continue; }
            $werteJson[] = ['achse_id' => $aid, 'achse' => $achse['name'], 'wert_id' => $wid, 'wert' => $wert['wert']];
            $k = $kuerzel[$wid] ?? null;
            if ($k === null || $k === '') {
                $ohne = count(array_filter($achse['werte'], fn($w) => ($kuerzel[(int) $w['wert_id']] ?? '') === ''));
                if ($ohne > 1) {
                    $fehler[] = "„{$achse['name']}: {$wert['wert']}“ hat kein Kürzel (Artikel → Schild-Bausteine). In dieser Achse fehlen mehrere Kürzel, die Nummer wäre nicht eindeutig.";
                }
                continue; // einziger Wert ohne Kürzel in der Achse -> weglassen (z.B. "nein, Danke")
            }
            $teile[] = $k;
            $namen[] = $wert['wert'];
        }
        if ($fehler) return ['fehler' => $fehler];

        $preis = $this->konfig->berechnePreis($layoutId, array_values(array_intersect_key($gewaehlt, $sichtbar)));
        if (!$preis['erfolg']) return ['fehler' => $preis['fehler']];

        $nummer = $layout['artikelnummer'] . ($teile ? '-' . implode('-', $teile) : '');
        if (mb_strlen($nummer) > 30) {
            return ['fehler' => ["Die Artikelnummer „{$nummer}“ hat " . mb_strlen($nummer) . " Zeichen, erlaubt sind 30. Bitte kürzere Kürzel vergeben (Artikel → Schild-Bausteine)."]];
        }
        $vorhanden = $this->artikelRepo->findByArtikelnummer($nummer);
        if ($vorhanden && !$this->db->query("SELECT 1 FROM konfig_fertige_artikel WHERE artikel_id = " . (int) $vorhanden['id'])->fetchColumn()) {
            return ['fehler' => ["Die Artikelnummer „{$nummer}“ gehört schon einem anderen Artikel ({$vorhanden['name']}), der kein fertiges Schild ist."]];
        }
        $name = $layout['name'] . ($namen ? ' – ' . implode(', ', $namen) : '');

        // Material-Hinweis (nur Info: ein fertiges Schild ist ja schon gebaut)
        $ohneMaterial = [];
        foreach ($konfiguration as $achse) foreach ($achse['werte'] as $w) {
            if (in_array((int) $w['wert_id'], $gewaehlt, true) && isset($sichtbar[(int) $achse['achse_id']]) && $w['verfuegbar'] === false) $ohneMaterial[] = $w['rohmaterial'];
        }

        return [
            'fehler'        => [],
            'artikelnummer' => $nummer,
            'name'          => mb_substr($name, 0, 255),
            'brutto'        => $preis['brutto'],
            'netto'         => $preis['netto'],
            'vorhanden'     => $vorhanden ? ['id' => (int) $vorhanden['id'], 'aktiv' => (int) $this->db->query("SELECT aktiv FROM artikel WHERE id = " . (int) $vorhanden['id'])->fetchColumn()] : null,
            'werte'         => $werteJson,
            'ohne_material' => array_values(array_unique(array_filter($ohneMaterial))),
        ];
    }

    /**
     * Legt das fertige Schild an (oder nimmt den vorhandenen Artikel) und bucht $menge ein.
     * Rückgabe: ['artikel_id', 'artikelnummer', 'neu' => bool, 'menge'].
     */
    public function anlegen(int $layoutId, array $wertIds, float $menge, int $lagerId, int $kategorieId, int $benutzerId): array
    {
        $v = $this->vorschau($layoutId, $wertIds);
        if ($v['fehler']) throw new RuntimeException(implode(' ', $v['fehler']));
        if ($menge < 0) throw new RuntimeException('Die Menge darf nicht negativ sein.');
        if ($menge > 0 && !$lagerId) throw new RuntimeException('Bitte ein Lager wählen.');

        $layout = $this->artikelRepo->findById($layoutId);
        $neu = false;
        $this->db->beginTransaction();
        try {
            if ($v['vorhanden']) {
                $artikelId = $v['vorhanden']['id'];
                if (!$v['vorhanden']['aktiv']) $this->artikelRepo->setArtikelAktiv($artikelId, 1);
            } else {
                if (!$kategorieId) throw new RuntimeException('Bitte die Kategorie für fertige Schilder wählen.');
                $artikelId = $this->artikelRepo->insert([
                    'artikelnummer'          => $v['artikelnummer'],
                    'name'                   => $v['name'],
                    'hersteller_id'          => $layout['hersteller_id'] ?? null,
                    'steuerklasse_id'        => $layout['steuerklasse_id'],
                    'artikel_gruppe_id'      => $layout['artikel_gruppe_id'] ?? null,
                    'artikeltyp_id'          => $layout['artikeltyp_id'],
                    'kurzbeschreibung'       => $this->beschreibung($v['werte']),
                    'beschreibung'           => $layout['beschreibung'] ?? null,
                    'einheit_id'             => $layout['einheit_id'] ?? null,
                    'inhalt_menge'           => null,
                    'inhalt_einheit'         => null,
                    'gewicht_artikel'        => $layout['gewicht_artikel'] ?? null,
                    'gewicht_versand'        => $layout['gewicht_versand'] ?? null,
                    'laenge'                 => $layout['laenge'] ?? null,
                    'breite'                 => $layout['breite'] ?? null,
                    'hoehe'                  => $layout['hoehe'] ?? null,
                    'herkunftsland'          => $layout['herkunftsland'] ?? null,
                    'taric_code'             => $layout['taric_code'] ?? null,
                    'grundpreis_bezugsmenge' => null,
                    'grundpreis_anzeigen'    => 0,
                    'charge_pflicht'         => 0,
                    'aktiv'                  => 1,
                ]);
                $kgId = (int) ($this->db->query("SELECT id FROM kundengruppen WHERE ist_standard = 1 LIMIT 1")->fetchColumn() ?: 1);
                $this->artikelRepo->insertPreis($artikelId, (float) $v['brutto'], (float) $v['netto'], $kgId);
                $this->db->prepare("INSERT IGNORE INTO artikel_kategorien (artikel_id, kategorie_id) VALUES (?, ?)")->execute([$artikelId, $kategorieId]);
                $this->db->prepare("INSERT INTO konfig_fertige_artikel (artikel_id, layout_artikel_id, werte_json) VALUES (?, ?, ?)")
                    ->execute([$artikelId, $layoutId, json_encode($v['werte'], JSON_UNESCAPED_UNICODE)]);
                // Shop-Kanäle wie das Layout
                $syncRepo = new ShopSyncRepository();
                $kanaele = $this->db->prepare("SELECT shop_id FROM artikel_shops WHERE artikel_id = ? AND aktiv = 1");
                $kanaele->execute([$layoutId]);
                foreach ($kanaele->fetchAll(PDO::FETCH_COLUMN) as $shopId) $syncRepo->upsertZuweisung($artikelId, (int) $shopId, true);
                $neu = true;
            }
            if ($kategorieId > 0) {
                $this->db->prepare("UPDATE system_einstellungen SET wert = ? WHERE schluessel = 'konfig_fertig_kategorie_id'")->execute([(string) $kategorieId]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        if ($menge > 0) {
            $erg = (new LagerService())->wareneingang([
                'artikel_id'  => $artikelId,
                'lager_id'    => $lagerId,
                'menge'       => $menge,
                'referenz'    => 'Fertiges Schild',
                'notiz'       => 'Fertiges Schild aus ' . $layout['artikelnummer'],
                'benutzer_id' => $benutzerId,
            ]);
            if (empty($erg['erfolg'])) throw new RuntimeException('Artikel ' . ($neu ? 'angelegt' : 'gefunden') . ', aber Einbuchen fehlgeschlagen: ' . implode(' ', $erg['fehler'] ?? []));
        }
        return ['artikel_id' => $artikelId, 'artikelnummer' => $v['artikelnummer'], 'neu' => $neu, 'menge' => $menge];
    }

    private function beschreibung(array $werte): string
    {
        return '<p>' . implode('<br>', array_map(fn($w) => htmlspecialchars($w['achse'] . ': ' . $w['wert']), $werte)) . '</p>';
    }
}
