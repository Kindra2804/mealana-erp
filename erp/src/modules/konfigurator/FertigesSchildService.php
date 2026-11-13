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
 * - Sonder-Eintrag (2026-11-13): statt eines Werts "Sonder …" mit Freitext, Kürzel (Vorgabe S) und Aufpreis,
 *   z.B. Sonderfarbe MG, Holzdicke. Ein Schild mit Sonder-Eintrag ist IMMER ein eigener Artikel (Jacky):
 *   ist die Nummer schon vergeben, wird -2, -3 … angehängt. Der Freitext steht im Namen und im Aufbau
 *   (werte_json, wert_id = null) und damit auch auf Kuvert-Etikett und Preisschild.
 * - Personalisierung (Wert mit Texteingabe, Migr. 236): Text ist Pflicht und steht im Aufbau als
 *   'Metallschild „Andreas“'. Gleiche Kombination + gleicher Text = derselbe Artikel (Abgleich über
 *   werte_json), anderer Text = neuer Artikel mit freier Nummer (-2, -3 …).
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
                   COALESCE((SELECT SUM(lb.bestand) FROM lagerbestand lb JOIN lager l ON l.id = lb.lager_id
                               AND (l.lager_beziehung = 'eigen' OR (l.lager_beziehung = 'partner_bestand' AND l.partner_id = a.partner_id))
                             WHERE lb.artikel_id = a.id), 0) AS bestand
            FROM konfig_fertige_artikel f JOIN artikel a ON a.id = f.artikel_id
            WHERE f.layout_artikel_id = ? ORDER BY a.artikelnummer");
        $st->execute([$layoutId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Rechnet Artikelnummer, Name, Preis aus, ohne etwas zu ändern.
     * $wertIds = [achse_id => wert_id | 'sonder'] (nur sichtbare Achsen),
     * $sonder  = [achse_id => ['text' => .., 'kuerzel' => .., 'aufpreis' => ..]] für die Achsen mit 'sonder'.
     * $texte   = [achse_id => Personalisierungstext] für Werte mit Texteingabe.
     * Rückgabe mit 'fehler' => [] wenn ok.
     */
    public function vorschau(int $layoutId, array $wertIds, array $sonder = [], array $texte = []): array
    {
        $layout = $this->artikelRepo->findById($layoutId);
        if (!$layout || empty($layout['ist_konfigurierbar'])) return ['fehler' => ['Das ist kein konfigurierbarer Artikel.']];

        $konfiguration = $this->konfig->getKonfiguration($layoutId);
        $kuerzel = $this->bausteine->kuerzelJeWert($layoutId);
        $sonderAchsen = array_filter($wertIds, fn($v) => $v === 'sonder');
        $gewaehlt = array_map('intval', array_filter($wertIds, fn($v) => $v !== 'sonder' && (int) $v > 0));
        $fehler = [];
        $teile = [];
        $namen = [];
        $werteJson = [];
        $sichtbar = [];
        $sonderSumme = 0.0;
        $mitText = false;
        foreach ($konfiguration as $achse) {
            $aid = (int) $achse['achse_id'];
            if ($achse['bedingung'] && (int) ($gewaehlt[$achse['bedingung']['achse_id']] ?? 0) !== (int) $achse['bedingung']['wert_id']) continue;
            if (isset($sonderAchsen[$aid])) {
                $e = $sonder[$aid] ?? [];
                $text = trim((string) ($e['text'] ?? ''));
                $k = trim((string) ($e['kuerzel'] ?? '')) ?: 'S';
                $aufpreisRoh = trim(str_replace(',', '.', (string) ($e['aufpreis'] ?? '')));
                if ($text === '') { $fehler[] = "„{$achse['name']}: Sonder“ — bitte den Text eingeben (z.B. Farbe, Maß)."; continue; }
                if (mb_strlen($text) > 60) { $fehler[] = "„{$achse['name']}: Sonder“ — Text höchstens 60 Zeichen."; continue; }
                if (!preg_match('/^[A-Za-z0-9]{1,12}$/', $k)) { $fehler[] = "„{$achse['name']}: Sonder“ — Kürzel nur Buchstaben und Ziffern, höchstens 12."; continue; }
                if ($aufpreisRoh !== '' && (!is_numeric($aufpreisRoh) || (float) $aufpreisRoh < 0)) { $fehler[] = "„{$achse['name']}: Sonder“ — Aufpreis bitte als Betrag ab 0 eingeben."; continue; }
                $aufpreis = round((float) $aufpreisRoh, 2);
                $sonderSumme += $aufpreis;
                $werteJson[] = ['achse_id' => $aid, 'achse' => $achse['name'], 'wert_id' => null, 'wert' => $text, 'sonder' => true, 'aufpreis' => $aufpreis];
                $teile[] = $k;
                $namen[] = $text;
                continue;
            }
            $sichtbar[$aid] = true;
            $wid = (int) ($gewaehlt[$aid] ?? 0);
            if (!$wid) { $fehler[] = "Bitte „{$achse['name']}“ wählen."; continue; }
            $wert = null;
            foreach ($achse['werte'] as $w) if ((int) $w['wert_id'] === $wid) $wert = $w;
            if (!$wert) { $fehler[] = "Ungültige Auswahl bei „{$achse['name']}“."; continue; }
            $anzeige = $wert['wert'];
            if (!empty($wert['texteingabe'])) {
                $text = trim((string) ($texte[$aid] ?? ''));
                $max = (int) ($wert['max_zeichen'] ?: KonfigBausteineService::MAX_ZEICHEN_STANDARD);
                if ($text === '') { $fehler[] = "„{$achse['name']}: {$wert['wert']}“ — bitte den Text eingeben."; continue; }
                if (mb_strlen($text) > $max) { $fehler[] = "„{$achse['name']}“ — Text höchstens {$max} Zeichen."; continue; }
                $anzeige = $wert['wert'] . ' „' . $text . '“';
                $mitText = true;
            }
            $werteJson[] = ['achse_id' => $aid, 'achse' => $achse['name'], 'wert_id' => $wid, 'wert' => $anzeige]
                         + (!empty($wert['texteingabe']) ? ['text' => $text] : []);
            $k = $kuerzel[$wid] ?? null;
            if ($k === null || $k === '') {
                $ohne = count(array_filter($achse['werte'], fn($w) => ($kuerzel[(int) $w['wert_id']] ?? '') === ''));
                if ($ohne > 1) {
                    $fehler[] = "„{$achse['name']}: {$wert['wert']}“ hat kein Kürzel (Artikel → Schild-Bausteine). In dieser Achse fehlen mehrere Kürzel, die Nummer wäre nicht eindeutig.";
                }
                continue; // einziger Wert ohne Kürzel in der Achse -> weglassen (z.B. "nein, Danke")
            }
            $teile[] = $k;
            $namen[] = $anzeige;
        }
        if ($fehler) return ['fehler' => $fehler];

        $preis = $this->konfig->berechnePreis($layoutId, array_values(array_intersect_key($gewaehlt, $sichtbar)));
        if (!$preis['erfolg']) return ['fehler' => $preis['fehler']];
        $istSonder = (bool) $sonderAchsen;
        if ($istSonder) {
            $preis['brutto'] = round($preis['brutto'] + $sonderSumme, 2);
            $preis['netto']  = round($preis['brutto'] / (1 + (float) $preis['steuer_prozent'] / 100), 4);
        }

        $nummer = $layout['artikelnummer'] . ($teile ? '-' . implode('-', $teile) : '');
        if (mb_strlen($nummer) > 30) {
            return ['fehler' => ["Die Artikelnummer „{$nummer}“ hat " . mb_strlen($nummer) . " Zeichen, erlaubt sind 30. Bitte kürzere Kürzel vergeben (Artikel → Schild-Bausteine)."]];
        }
        $vorhanden = null;
        if ($mitText && !$istSonder) {
            // Personalisiert: gleiche Kombination + gleicher Text = derselbe Artikel
            $st = $this->db->prepare("SELECT a.id, a.artikelnummer, a.name FROM konfig_fertige_artikel f JOIN artikel a ON a.id = f.artikel_id
                                      WHERE f.layout_artikel_id = ? AND f.werte_json = ? LIMIT 1");
            $st->execute([$layoutId, json_encode($werteJson, JSON_UNESCAPED_UNICODE)]);
            $vorhanden = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($vorhanden) $nummer = $vorhanden['artikelnummer'];
        }
        if (($istSonder || $mitText) && !$vorhanden) {
            // Sonderschild / neuer personalisierter Text = eigener Artikel: freie Nummer (Basis, dann -2, -3 …)
            $basis = $nummer;
            for ($n = 2; $this->artikelRepo->findByArtikelnummer($nummer); $n++) $nummer = $basis . '-' . $n;
            if (mb_strlen($nummer) > 30) {
                return ['fehler' => ["Die Artikelnummer „{$nummer}“ hat " . mb_strlen($nummer) . " Zeichen, erlaubt sind 30. Bitte kürzere Kürzel verwenden."]];
            }
        } elseif (!$vorhanden) {
            $vorhanden = $this->artikelRepo->findByArtikelnummer($nummer);
            if ($vorhanden && !$this->db->query("SELECT 1 FROM konfig_fertige_artikel WHERE artikel_id = " . (int) $vorhanden['id'])->fetchColumn()) {
                return ['fehler' => ["Die Artikelnummer „{$nummer}“ gehört schon einem anderen Artikel ({$vorhanden['name']}), der kein fertiges Schild ist."]];
            }
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
            'sonder'        => $istSonder,
            'ohne_material' => array_values(array_unique(array_filter($ohneMaterial))),
        ];
    }

    /**
     * Legt das fertige Schild an (oder nimmt den vorhandenen Artikel) und bucht $menge ein.
     * Rückgabe: ['artikel_id', 'artikelnummer', 'neu' => bool, 'menge'].
     */
    public function anlegen(int $layoutId, array $wertIds, float $menge, int $lagerId, int $kategorieId, int $benutzerId, array $sonder = [], array $texte = []): array
    {
        $v = $this->vorschau($layoutId, $wertIds, $sonder, $texte);
        if ($v['fehler']) throw new RuntimeException(implode(' ', $v['fehler']));
        if ($menge < 0) throw new RuntimeException('Die Menge darf nicht negativ sein.');
        $layout = $this->artikelRepo->findById($layoutId);
        // Eigentümer vom Layout (Migration 237): gehört das Layout einem Partner, gehört ihm auch das Schild,
        // eingebucht wird in sein Partner-Lager (das gewählte Lager gilt nur für die Hauptfirma)
        $eig = $this->db->prepare("SELECT a.partner_id, a.partner_modus, l.id AS lager_id FROM artikel a
                                   LEFT JOIN lager l ON l.partner_id = a.partner_id AND l.lager_beziehung = 'partner_bestand'
                                   WHERE a.id = ?");
        $eig->execute([$layoutId]);
        $eig = $eig->fetch(PDO::FETCH_ASSOC) ?: ['partner_id' => null];
        if ($eig['partner_id']) {
            if (!$eig['lager_id']) throw new RuntimeException('Der Partner dieses Layouts hat noch kein Lager (Partner → Detail → Lager anlegen).');
            $lagerId = (int) $eig['lager_id'];
        }
        if ($menge > 0 && !$lagerId) throw new RuntimeException('Bitte ein Lager wählen.');
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
                if ($eig['partner_id']) {
                    $this->db->prepare("UPDATE artikel SET partner_id = ?, partner_modus = ? WHERE id = ?")
                             ->execute([(int) $eig['partner_id'], $eig['partner_modus'], $artikelId]);
                }
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
