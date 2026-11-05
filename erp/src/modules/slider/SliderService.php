<?php

require_once __DIR__ . '/SliderRepository.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../artikel/BildVerarbeitung.php';
require_once __DIR__ . '/../shop/WooCommerceClient.php';

/**
 * SliderService – Geschäftslogik für den Shop-Slider (Migration 224).
 *
 * Ablauf:
 *  1. Slides werden je Shop im ERP gepflegt (speichere/kopiere/loesche/sortiere).
 *     Jede Änderung setzt shops.slider_geaendert = 1 („noch nicht übertragen“).
 *  2. uebertrage() lädt neue/geänderte Bilder in die WP-Mediathek und schickt die
 *     komplette Liste der sichtbaren Slides an das WP-Plugin „NAHTLOS Connector“
 *     (POST /wp-json/nahtlos/v1/slider). Das Plugin ersetzt seinen Stand komplett.
 *  3. Zeitsteuerung macht das Plugin selbst (von/bis), weil das ERP vom Shop aus
 *     nicht erreichbar ist. Aktions-Slides bekommen dafür den Zeitraum der Aktion
 *     mit; ändert sich die Aktion, überträgt der Cron (cron/shop_sync.php) neu.
 *
 * Button-Ziele werden als Woo-ID übertragen (Kategorie-Term, Produkt, Hersteller-Term),
 * das Plugin baut den Link selbst — so stimmen Links auch nach Slug-Änderungen.
 */
class SliderService
{
    private SliderRepository $repo;

    public const UPLOAD_ORDNER = __DIR__ . '/../../../public/uploads/slider/';
    public const ZIEL_TYPEN = ['kategorie', 'artikel', 'hersteller', 'url'];

    public function __construct()
    {
        $this->repo = new SliderRepository();
    }

    public function getRepo(): SliderRepository
    {
        return $this->repo;
    }

    // ── Speichern ───────────────────────────────────────────────────────────

    /**
     * Legt eine Slide an oder aktualisiert sie (id > 0). Bilder kommen getrennt über speichereBild().
     * @return array{erfolg:bool, id?:int, fehler?:array}
     */
    public function speichere(array $daten): array
    {
        $id = (int)($daten['id'] ?? 0);
        $alt = $id > 0 ? $this->repo->findById($id) : null;
        if ($id > 0 && !$alt) return ['erfolg' => false, 'fehler' => ['Slide nicht gefunden']];

        $daten = array_intersect_key($daten, array_flip(SliderRepository::FELDER));
        foreach ($daten as $k => $v) {
            if (is_string($v)) $daten[$k] = trim($v) === '' ? null : trim($v);
        }
        if ($alt) $daten['shop_id'] = $alt['shop_id'];
        $daten['ist_aktiv'] = !empty($daten['ist_aktiv']) ? 1 : 0;
        // Bei „Bild + Text“ ist der Titel die Bezeichnung in der Liste, sonst das eigene Feld
        if (($daten['art'] ?? '') === 'gestaltet' || empty($daten['bezeichnung'])) {
            $daten['bezeichnung'] = $daten['titel'] ?? null;
        }

        $fehler = $this->validiere($daten);
        if ($fehler) return ['erfolg' => false, 'fehler' => $fehler];
        $daten = $this->normalisiere($daten);

        if ($alt) {
            $this->repo->update($id, $daten);
            Logger::log('slider.bearbeiten', 'shop_slides', $id, ['bezeichnung' => $daten['bezeichnung']]);
        } else {
            $id = $this->repo->insert($daten);
            Logger::log('slider.anlegen', 'shop_slides', $id, ['bezeichnung' => $daten['bezeichnung'], 'shop_id' => $daten['shop_id']]);
        }
        $this->repo->markiereGeaendert((int)$daten['shop_id']);
        return ['erfolg' => true, 'id' => $id];
    }

    private function validiere(array $d): array
    {
        $f = [];
        if (empty($d['shop_id']) || !$this->repo->findShop((int)$d['shop_id'])) $f[] = 'Bitte einen Shop wählen.';
        if (!in_array($d['position'] ?? '', ['startseite', 'kategorie'], true)) $f[] = 'Ungültige Position.';
        if (($d['position'] ?? '') === 'kategorie' && empty($d['kategorie_id'])) $f[] = 'Bitte die Kategorie wählen, auf deren Seite das Banner erscheint.';
        if (!in_array($d['art'] ?? '', ['gestaltet', 'fertiges_bild'], true)) $f[] = 'Ungültige Art.';
        if (empty($d['bezeichnung'])) $f[] = 'Bitte einen Titel oder eine Bezeichnung eingeben.';
        if (($d['art'] ?? '') === 'gestaltet' && empty($d['titel'])) $f[] = 'Der Titel fehlt.';

        foreach ([1, 2] as $n) {
            $text = $d["button{$n}_text"] ?? null;
            $typ  = $d["button{$n}_ziel_typ"] ?? null;
            // Bei fertigem Bild ist Button 1 der Link der ganzen Slide (ohne Text)
            $brauchtZiel = $text !== null || ($n === 1 && ($d['art'] ?? '') === 'fertiges_bild' && $typ !== null);
            if (!$brauchtZiel) continue;
            if (!in_array($typ, self::ZIEL_TYPEN, true)) { $f[] = "Button $n: bitte ein Ziel wählen."; continue; }
            if ($typ === 'url') {
                $url = $d["button{$n}_url"] ?? '';
                if (!preg_match('#^(https?://|/)#', $url)) $f[] = "Button $n: die Adresse muss mit https:// oder / beginnen.";
            } elseif (empty($d["button{$n}_ziel_id"])) {
                $f[] = "Button $n: bitte " . ['kategorie' => 'die Kategorie', 'artikel' => 'den Artikel', 'hersteller' => 'den Hersteller'][$typ] . ' wählen.';
            }
        }

        $sichtbar = $d['sichtbar'] ?? '';
        if (!in_array($sichtbar, ['immer', 'zeitraum', 'aktion'], true)) $f[] = 'Ungültige Sichtbarkeit.';
        if ($sichtbar === 'zeitraum') {
            if (empty($d['gueltig_von']) && empty($d['gueltig_bis'])) $f[] = 'Bitte „von“ und/oder „bis“ angeben.';
            if (!empty($d['gueltig_von']) && !empty($d['gueltig_bis']) && strtotime($d['gueltig_von']) >= strtotime($d['gueltig_bis'])) {
                $f[] = '„bis“ muss nach „von“ liegen.';
            }
        }
        if ($sichtbar === 'aktion' && empty($d['aktion_id'])) $f[] = 'Bitte die Aktion wählen.';
        return $f;
    }

    /** Felder leeren, die zur gewählten Variante nicht passen (keine Altlasten in der DB). */
    private function normalisiere(array $d): array
    {
        if ($d['position'] !== 'kategorie') $d['kategorie_id'] = null;
        if ($d['art'] === 'fertiges_bild') {
            $d['kicker'] = $d['text'] = $d['button2_text'] = $d['button2_ziel_typ'] = $d['button2_ziel_id'] = $d['button2_url'] = null;
            $d['button1_text'] = null;
        }
        foreach ([1, 2] as $n) {
            $typ = $d["button{$n}_ziel_typ"] ?? null;
            $ohneText = ($d["button{$n}_text"] ?? null) === null && !($n === 1 && $d['art'] === 'fertiges_bild');
            if ($typ === null || $ohneText) {
                $d["button{$n}_ziel_typ"] = $d["button{$n}_ziel_id"] = $d["button{$n}_url"] = null;
                continue;
            }
            if ($typ === 'url') $d["button{$n}_ziel_id"] = null; else $d["button{$n}_url"] = null;
        }
        if ($d['sichtbar'] !== 'zeitraum') $d['gueltig_von'] = $d['gueltig_bis'] = null;
        if ($d['sichtbar'] !== 'aktion') $d['aktion_id'] = null;
        foreach (['gueltig_von', 'gueltig_bis'] as $k) {
            if (!empty($d[$k])) $d[$k] = date('Y-m-d H:i:s', strtotime($d[$k]));
        }
        foreach (['kategorie_id', 'button1_ziel_id', 'button2_ziel_id', 'aktion_id'] as $k) {
            $d[$k] = !empty($d[$k]) ? (int)$d[$k] : null;
        }
        return $d;
    }

    /**
     * Speichert ein hochgeladenes Bild (variante desktop|mobil) unter uploads/slider/{id}/.
     * @param array $datei Eintrag aus $_FILES
     */
    public function speichereBild(int $id, string $variante, array $datei): array
    {
        $slide = $this->repo->findById($id);
        if (!$slide) return ['erfolg' => false, 'fehler' => ['Slide nicht gefunden']];
        if (($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['erfolg' => false, 'fehler' => ['Bild-Upload fehlgeschlagen (Code ' . ($datei['error'] ?? '?') . ')']];
        }
        if ($datei['size'] > 15 * 1024 * 1024) return ['erfolg' => false, 'fehler' => ['Bild zu groß (max. 15 MB)']];
        $mime = mime_content_type($datei['tmp_name']);
        if (!in_array($mime, BildVerarbeitung::ERLAUBTE_MIMES, true)) return ['erfolg' => false, 'fehler' => ['Nur JPG, PNG und WEBP erlaubt']];

        $ordner = self::UPLOAD_ORDNER . $id . '/';
        if (!is_dir($ordner)) mkdir($ordner, 0755, true);
        $ziel = $ordner . $variante . '_' . time() . '_' . random_int(1000, 9999) . '.jpg';
        $gespeichert = BildVerarbeitung::verkleinereUndSpeichere($datei['tmp_name'], $mime, $ziel);
        if ($gespeichert === false) return ['erfolg' => false, 'fehler' => ['Bild konnte nicht verarbeitet werden']];

        $alt = $slide[$variante === 'mobil' ? 'bild_mobil' : 'bild_desktop'];
        if ($alt && is_file($ordner . $alt)) unlink($ordner . $alt);
        $this->repo->setBild($id, $variante, basename($gespeichert));
        $this->repo->markiereGeaendert((int)$slide['shop_id']);
        return ['erfolg' => true];
    }

    public function entferneMobilBild(int $id): void
    {
        $slide = $this->repo->findById($id);
        if (!$slide || !$slide['bild_mobil']) return;
        $pfad = self::UPLOAD_ORDNER . $id . '/' . $slide['bild_mobil'];
        if (is_file($pfad)) unlink($pfad);
        $this->repo->setBild($id, 'mobil', null);
        $this->repo->markiereGeaendert((int)$slide['shop_id']);
    }

    public function setAktiv(int $id, bool $aktiv): array
    {
        $slide = $this->repo->findById($id);
        if (!$slide) return ['erfolg' => false, 'fehler' => 'Slide nicht gefunden'];
        $this->repo->setAktiv($id, $aktiv);
        $this->repo->markiereGeaendert((int)$slide['shop_id']);
        Logger::log($aktiv ? 'slider.einschalten' : 'slider.ausschalten', 'shop_slides', $id, ['bezeichnung' => $slide['bezeichnung']]);
        return ['erfolg' => true];
    }

    public function loesche(int $id): array
    {
        $slide = $this->repo->findById($id);
        if (!$slide) return ['erfolg' => false, 'fehler' => 'Slide nicht gefunden'];
        $ordner = self::UPLOAD_ORDNER . $id . '/';
        foreach ([$slide['bild_desktop'], $slide['bild_mobil']] as $b) {
            if ($b && is_file($ordner . $b)) unlink($ordner . $b);
        }
        if (is_dir($ordner)) @rmdir($ordner);
        $this->repo->delete($id);
        $this->repo->markiereGeaendert((int)$slide['shop_id']);
        Logger::log('slider.loeschen', 'shop_slides', $id, ['bezeichnung' => $slide['bezeichnung']]);
        return ['erfolg' => true];
    }

    public function sortiere(int $shopId, array $ids): array
    {
        $this->repo->sortiere($ids);
        $this->repo->markiereGeaendert($shopId);
        return ['erfolg' => true];
    }

    /**
     * Kopiert eine Slide (auch in einen anderen Shop). Die Kopie startet ausgeschaltet,
     * damit sie nicht ungeprüft im Shop erscheint. Ziele (Kategorie/Artikel/Hersteller)
     * bleiben ERP-IDs und werden beim Übertragen im Zielshop aufgelöst.
     */
    public function kopiere(int $id, int $zielShopId): array
    {
        $slide = $this->repo->findById($id);
        if (!$slide) return ['erfolg' => false, 'fehler' => 'Slide nicht gefunden'];
        if (!$this->repo->findShop($zielShopId)) return ['erfolg' => false, 'fehler' => 'Ziel-Shop nicht gefunden'];

        $neu = array_intersect_key($slide, array_flip(SliderRepository::FELDER));
        $neu['shop_id'] = $zielShopId;
        $neu['ist_aktiv'] = 0;
        if ($zielShopId === (int)$slide['shop_id']) $neu['bezeichnung'] = mb_substr('Kopie: ' . $slide['bezeichnung'], 0, 100);
        $neuId = $this->repo->insert($neu);

        $quelle = self::UPLOAD_ORDNER . $id . '/';
        $ziel   = self::UPLOAD_ORDNER . $neuId . '/';
        foreach (['desktop' => $slide['bild_desktop'], 'mobil' => $slide['bild_mobil']] as $variante => $datei) {
            if (!$datei || !is_file($quelle . $datei)) continue;
            if (!is_dir($ziel)) mkdir($ziel, 0755, true);
            copy($quelle . $datei, $ziel . $datei);
            $this->repo->setBild($neuId, $variante, $datei);
        }
        $this->repo->markiereGeaendert($zielShopId);
        Logger::log('slider.kopieren', 'shop_slides', $neuId, ['von' => $id, 'shop_id' => $zielShopId]);
        return ['erfolg' => true, 'id' => $neuId];
    }

    // ── Status für die Liste ────────────────────────────────────────────────

    /**
     * Sichtbarkeits-Status einer Slide (für die Liste), gleiche Regeln wie das Plugin:
     * aus | kein_bild | geplant | sichtbar | abgelaufen
     */
    public static function status(array $s): string
    {
        if (!$s['ist_aktiv']) return 'aus';
        if (!$s['bild_desktop']) return 'kein_bild';
        [$von, $bis] = self::zeitraum($s);
        if ($s['sichtbar'] === 'aktion' && (!$s['aktion_id'] || !$s['aktion_gestartet'])) return 'aus';
        $jetzt = time();
        if ($bis !== null && strtotime($bis) < $jetzt) return 'abgelaufen';
        if ($von !== null && strtotime($von) > $jetzt) return 'geplant';
        return 'sichtbar';
    }

    /** [von, bis] als 'Y-m-d H:i:s' oder null, bei Aktion aus dem Aktionszeitraum. */
    public static function zeitraum(array $s): array
    {
        if ($s['sichtbar'] === 'zeitraum') return [$s['gueltig_von'], $s['gueltig_bis']];
        if ($s['sichtbar'] === 'aktion') {
            return [
                !empty($s['aktion_von']) ? $s['aktion_von'] . ' 00:00:00' : null,
                !empty($s['aktion_bis']) ? $s['aktion_bis'] . ' 23:59:59' : null,
            ];
        }
        return [null, null];
    }

    // ── Übertragen an den Shop ─────────────────────────────────────────────

    /**
     * Überträgt alle Slides eines Shops an das Plugin.
     * @return array{erfolg:bool, fehler?:string, uebertragen?:int, hinweise?:array}
     */
    public function uebertrage(int $shopId, ?int $benutzerId = null): array
    {
        $shop = $this->repo->findShop($shopId);
        if (!$shop) return ['erfolg' => false, 'fehler' => 'Shop nicht gefunden'];
        if (!$shop['wc_url'] || !$shop['wp_username'] || !$shop['wp_app_password']) {
            return ['erfolg' => false, 'fehler' => 'Für diesen Shop fehlt die WordPress-Anmeldung (Einstellungen → Kanäle).'];
        }
        $client = new WooCommerceClient($shop['wc_url'], (string)$shop['wc_key'], (string)$shop['wc_secret'], $shop['wp_username'], $shop['wp_app_password']);

        $hinweise = [];
        $slides = [];
        try {
            // Erst prüfen, ob das Plugin da ist — sonst landen Bilder umsonst in der Mediathek
            $client->connector('GET', 'status');
            foreach ($this->repo->findByShop($shopId) as $s) {
                $status = self::status($s);
                if (in_array($status, ['aus', 'kein_bild', 'abgelaufen'], true)) {
                    if ($status === 'kein_bild' && $s['ist_aktiv']) $hinweise[] = "„{$s['bezeichnung']}“: kein Bild — nicht übertragen.";
                    continue;
                }
                $eintrag = $this->baueSlide($s, $shopId, $client, $hinweise);
                if ($eintrag !== null) $slides[] = $eintrag;
            }

            $antwort = $client->connector('POST', 'slider', [
                'version'   => 1,
                'intervall' => (int)$shop['slider_intervall_sek'],
                'stand'     => date('Y-m-d H:i:s'),
                'slides'    => $slides,
            ]);
        } catch (Throwable $e) {
            $this->repo->markiereUebertragen($shopId, $e->getMessage());
            Logger::log('slider.uebertragen', 'shops', $shopId, ['fehler' => $e->getMessage()], $benutzerId, 'error');
            return ['erfolg' => false, 'fehler' => $e->getMessage(), 'hinweise' => $hinweise];
        }

        $this->repo->markiereUebertragen($shopId, null);
        Logger::log('slider.uebertragen', 'shops', $shopId, ['slides' => count($slides), 'hinweise' => $hinweise], $benutzerId);
        return ['erfolg' => true, 'uebertragen' => count($slides), 'hinweise' => $hinweise, 'antwort' => $antwort];
    }

    /** Eine Slide in das Plugin-Format bringen; lädt fehlende Bilder hoch. null = überspringen. */
    private function baueSlide(array $s, int $shopId, WooCommerceClient $client, array &$hinweise): ?array
    {
        $kategorieTerm = null;
        if ($s['position'] === 'kategorie') {
            $kategorieTerm = $this->repo->findExterneZielId('kategorie', (int)$s['kategorie_id'], $shopId);
            if ($kategorieTerm === null) {
                $hinweise[] = "„{$s['bezeichnung']}“: Kategorie „{$s['kategorie_name']}“ ist (noch) nicht im Shop — nicht übertragen.";
                return null;
            }
        }

        $bilder = [];
        foreach (['desktop', 'mobil'] as $variante) {
            $datei = $s['bild_' . $variante];
            if (!$datei) { $bilder[$variante] = null; continue; }
            if ($s["bild_{$variante}_synced"] !== $datei || !$s["bild_{$variante}_wp_id"]) {
                $media = $client->ladeBildHoch(self::UPLOAD_ORDNER . $s['id'] . '/' . $datei,
                    'slider-' . $s['id'] . '-' . $variante . '.' . pathinfo($datei, PATHINFO_EXTENSION),
                    (string)($s['alt_text'] ?: $s['titel'] ?: $s['bezeichnung']));
                $s["bild_{$variante}_wp_id"]  = (int)$media['id'];
                $s["bild_{$variante}_wp_url"] = (string)$media['source_url'];
                $this->repo->setBildWp((int)$s['id'], $variante, (int)$media['id'], (string)$media['source_url'], $datei);
            }
            $bilder[$variante] = ['id' => (int)$s["bild_{$variante}_wp_id"], 'url' => $s["bild_{$variante}_wp_url"]];
        }

        $buttons = [];
        foreach ([1, 2] as $n) {
            $typ = $s["button{$n}_ziel_typ"];
            if (!$typ) continue;
            $ziel = $typ === 'url' ? $s["button{$n}_url"] : $this->repo->findExterneZielId($typ, (int)$s["button{$n}_ziel_id"], $shopId);
            if ($ziel === null) {
                $hinweise[] = "„{$s['bezeichnung']}“: Ziel von Button $n ist (noch) nicht im Shop — Button weggelassen.";
                continue;
            }
            $buttons[] = ['text' => $s["button{$n}_text"], 'typ' => $typ === 'artikel' ? 'produkt' : $typ, 'ziel' => $ziel];
        }

        [$von, $bis] = self::zeitraum($s);
        return [
            'id'            => (int)$s['id'],
            'position'      => $s['position'],
            'kategorie'     => $kategorieTerm !== null ? (int)$kategorieTerm : null,
            'art'           => $s['art'],
            'kicker'        => $s['kicker'],
            'titel'         => $s['titel'],
            'text'          => $s['text'],
            'buttons'       => $buttons,
            'text_position' => $s['text_position'],
            'schrift'       => $s['schrift'],
            'bild'          => $bilder['desktop'],
            'bild_mobil'    => $bilder['mobil'],
            'alt'           => $s['alt_text'] ?: ($s['titel'] ?: $s['bezeichnung']),
            // mit Zeitzone, damit der Shop unabhängig von seiner WP-Zeitzone richtig umschaltet
            'von'           => $von !== null ? date('c', strtotime($von)) : null,
            'bis'           => $bis !== null ? date('c', strtotime($bis)) : null,
        ];
    }

    /**
     * Für den Cron: einmal täglich Shops mit Aktions-Slides neu übertragen (der Zeitraum
     * kann sich in der Aktion geändert haben). Nur wenn im ERP keine unübertragenen
     * Änderungen offen sind — halbfertige Slides gehen nie automatisch online, das
     * Übertragen bleibt ein bewusster Klick.
     * $systemBenutzerId = Jarvis (Cron hat keine Session, siehe Logger).
     */
    public function cronUebertrage(int $systemBenutzerId, ?callable $log = null): void
    {
        foreach ($this->repo->findShops() as $shop) {
            $faellig = (int)$shop['slider_geaendert'] === 0
                && $shop['slider_uebertragen_am'] !== null
                && date('Y-m-d', strtotime($shop['slider_uebertragen_am'])) < date('Y-m-d')
                && array_filter($this->repo->findByShop((int)$shop['id']), fn($s) => $s['sichtbar'] === 'aktion' && $s['ist_aktiv']);
            if (!$faellig) continue;
            $r = $this->uebertrage((int)$shop['id'], $systemBenutzerId);
            if ($log) $log("Slider {$shop['name']}: " . ($r['erfolg'] ? "{$r['uebertragen']} Slides übertragen" : 'FEHLER ' . $r['fehler']));
        }
    }
}
