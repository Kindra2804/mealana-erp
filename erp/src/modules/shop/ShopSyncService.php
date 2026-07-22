<?php

require_once __DIR__ . '/ShopSyncRepository.php';
require_once __DIR__ . '/WooCommerceClient.php';
require_once __DIR__ . '/../../core/logger.php';
require_once __DIR__ . '/../artikel/BilderRepository.php';

/**
 * ShopSyncService – synct Standard-Artikel UND Vater/Kind-Artikel nach WooCommerce.
 *
 * Aufgerufen vom Sync-Cron, ein Durchlauf pro Shop. Jeder Artikel wird einzeln
 * verarbeitet -- ein Fehler bei einem Artikel darf die anderen nicht blockieren
 * (daher try/catch pro Artikel, nicht um die ganze Schleife).
 *
 * Vater-Artikel mit Achsen werden als WooCommerce "Variable Product" gesynct,
 * ihre Kind-Artikel als "Variation" darunter (eigener Endpoint, eigenes
 * Payload-Format). Ein Kind kann erst synct werden, wenn sein Vater bereits
 * eine WooCommerce-Produkt-ID hat -- `findFaelligeArtikel()` liefert Väter
 * darum immer vor ihren Kindern, ein Kind ohne fertigen Vater wird in diesem
 * Durchlauf übersprungen und bleibt `pending` für den nächsten.
 */
class ShopSyncService
{
    private ShopSyncRepository $repo;
    private BilderRepository $bilderRepo;
    private int $jarvisId;

    public function __construct()
    {
        $this->repo = new ShopSyncRepository();
        $this->bilderRepo = new BilderRepository();
        // Läuft als Cron ohne Session -- Logger::log() braucht dann eine explizite
        // benutzer_id, sonst crasht der INSERT an aktivitaeten.benutzer_id NOT NULL
        // (gleiches Bug-Muster wie schon bei cron/mahnwesen.php und LagerService).
        $this->jarvisId = (int)Database::getInstance()
            ->query("SELECT id FROM benutzer WHERE username = 'system'")
            ->fetchColumn();
    }

    /** Synct alle fälligen Artikel für alle konfigurierten Shops. */
    public function syncAlleShops(): array
    {
        $ergebnis = [];
        foreach ($this->repo->findAktiveShops() as $shop) {
            $ergebnis[$shop['slug']] = $this->syncShop($shop);
        }
        return $ergebnis;
    }

    /** @return array{erfolg:int,fehler:int} */
    public function syncShop(array $shop): array
    {
        $client = new WooCommerceClient(
            $shop['wc_url'],
            $shop['wc_key'],
            $shop['wc_secret'],
            $shop['wp_username'],
            $shop['wp_app_password']
        );
        $erfolg = 0;
        $fehler = 0;

        $faelligeArtikel = $this->repo->findFaelligeArtikel((int)$shop['id']);

        // Kategorien MÜSSEN vor den Artikeln in WooCommerce existieren, sonst kann der
        // Artikel-Payload weiter unten (baueProduktPayload → findWcKategorieIds) keine
        // Kategorie-ID referenzieren. Ein Fehler bei einer Kategorie blockiert nicht den
        // ganzen Shop-Durchlauf -- der betroffene Artikel wird dann eben ohne diese
        // Kategorie synct (findWcKategorieIds liefert einfach eine ID weniger).
        $bereitsVersucht = [];
        foreach ($faelligeArtikel as $row) {
            foreach ($this->repo->findKategorieIdsFuerArtikel((int)$row['artikel_id']) as $kategorieId) {
                $kategorieId = (int)$kategorieId;
                if (isset($bereitsVersucht[$kategorieId])) continue;
                $bereitsVersucht[$kategorieId] = true;
                try {
                    $this->syncKategorieMitVorfahren($client, $kategorieId, (int)$shop['id']);
                } catch (Throwable $e) {
                    Logger::log('shop.kategorie_sync_fehler', 'kategorien', $kategorieId, [
                        'shop'  => $shop['slug'],
                        'fehler' => $e->getMessage(),
                    ], $this->jarvisId, 'error');
                }
            }
        }

        // Zweiter, unabhängiger Kategorie-Durchlauf: bereits angelegte Kategorien,
        // die sich seit dem letzten Sync geändert haben (Name/Beschreibung/
        // Oberkategorie), MÜSSEN auch dann nachgezogen werden, wenn gerade kein
        // Artikel dieser Kategorie fällig ist -- die Schleife oben deckt nur
        // "neu anlegen über einen fälligen Artikel" ab, nicht "bereits vorhanden,
        // aber geändert".
        foreach ($this->repo->findFaelligeKategorien((int)$shop['id']) as $kategorieId) {
            if (isset($bereitsVersucht[$kategorieId])) continue;
            $bereitsVersucht[$kategorieId] = true;
            try {
                $this->syncKategorieMitVorfahren($client, $kategorieId, (int)$shop['id']);
            } catch (Throwable $e) {
                Logger::log('shop.kategorie_sync_fehler', 'kategorien', $kategorieId, [
                    'shop'  => $shop['slug'],
                    'fehler' => $e->getMessage(),
                ], $this->jarvisId, 'error');
            }
        }

        foreach ($faelligeArtikel as $row) {
            try {
                $istKind = $row['vaterartikel_id'] !== null;
                $vaterId = $istKind ? (int)$row['vaterartikel_id'] : (int)$row['artikel_id'];

                // Idempotent (siehe syncAchsenFuerVater) -- bei einem Standard-Artikel
                // ohne Achsen findet die Methode einfach nichts zu tun.
                $this->syncAchsenFuerVater($client, $vaterId, (int)$shop['id']);

                // Bilder werden NICHT vom Vater geerbt (project_bilder_modul.md) --
                // jede Artikel-Zeile (Vater UND jedes Kind) hat eigene Bilder,
                // darum mit der eigenen artikel_id aufrufen, nicht $vaterId.
                $this->syncBilderFuerArtikel($client, (int)$row['artikel_id'], (int)$shop['id']);

                if ($istKind) {
                    if (!$row['vater_external_id']) {
                        // Vater noch nicht in WooCommerce vorhanden -- Kind bleibt
                        // 'pending' und wird im nächsten Durchlauf erneut versucht,
                        // sobald der Vater dann eine external_id hat.
                        continue;
                    }
                    $payload = $this->baueVariationPayload($row, (int)$shop['id']);
                    if ($row['external_id']) {
                        $wcObjekt = $client->aktualisiereVariation($row['vater_external_id'], $row['external_id'], $payload);
                    } else {
                        $wcObjekt = $client->erstelleVariation($row['vater_external_id'], $payload);
                    }
                } else {
                    if (!empty($row['hersteller_id'])) {
                        $this->syncHerstellerFuerArtikel($client, (int)$row['hersteller_id'], (int)$shop['id']);
                    }
                    $payload = $this->baueProduktPayload($row, (int)$shop['id']);
                    if ($row['external_id']) {
                        $wcObjekt = $client->aktualisiereProdukt($row['external_id'], $payload);
                    } else {
                        $wcObjekt = $client->erstelleProdukt($payload);
                    }
                }

                $this->repo->markiereSynced((int)$row['artikel_shop_id'], (string)$wcObjekt['id']);
                $erfolg++;
            } catch (Throwable $e) {
                $this->repo->markiereFehler((int)$row['artikel_shop_id'], $e->getMessage());
                Logger::log('shop.sync_fehler', 'artikel', (int)$row['artikel_id'], [
                    'shop'  => $shop['slug'],
                    'fehler' => $e->getMessage(),
                ], $this->jarvisId, 'error');
                $fehler++;
            }
        }

        return ['erfolg' => $erfolg, 'fehler' => $fehler];
    }

    /**
     * Stellt sicher, dass eine Kategorie + all ihre Vorfahren in WooCommerce existieren
     * (voller Pfad über `parent`, Entscheidung siehe db_design_entscheidungen.md). Bereits
     * angelegte Ebenen (externe_kategorie_id schon gespeichert) werden übersprungen -- nicht
     * erneut angelegt oder aktualisiert, das Feature deckt nur das erstmalige Anlegen ab.
     */
    private function syncKategorieMitVorfahren(WooCommerceClient $client, int $kategorieId, int $shopId): void
    {
        $wcParentId = 0;
        foreach ($this->repo->findKategorieMitVorfahren($kategorieId) as $kategorie) {
            $vorhandeneZuweisung = $this->repo->findKategorieShopZuweisung((int)$kategorie['id'], $shopId);
            if ($vorhandeneZuweisung && $vorhandeneZuweisung['externe_kategorie_id']) {
                $wcKategorieId = (int)$vorhandeneZuweisung['externe_kategorie_id'];

                // Nachziehen nur wenn seit dem letzten Sync tatsächlich etwas an
                // Name/Beschreibung/Oberkategorie geändert wurde (Change-Detection
                // analog zu artikel/artikel_shops) -- sonst bei jedem Cron-Lauf
                // unnötig dieselbe Kategorie erneut per API aktualisieren.
                $mussAktualisieren = $vorhandeneZuweisung['synced_at'] === null
                    || strtotime($kategorie['aktualisiert_am']) > strtotime($vorhandeneZuweisung['synced_at']);

                if ($mussAktualisieren) {
                    $client->aktualisiereKategorie((string)$wcKategorieId, [
                        'name'        => $kategorie['name'],
                        'description' => $kategorie['beschreibung'] ?? '',
                        'parent'      => $wcParentId,
                    ]);
                    $this->repo->upsertKategorieZuweisung((int)$kategorie['id'], $shopId, (string)$wcKategorieId);
                }

                $wcParentId = $wcKategorieId;
                continue;
            }
            $wcKategorie = $client->erstelleKategorie([
                'name'        => $kategorie['name'],
                'description' => $kategorie['beschreibung'] ?? '',
                'parent'      => $wcParentId,
            ]);
            $this->repo->upsertKategorieZuweisung((int)$kategorie['id'], $shopId, (string)$wcKategorie['id']);
            $wcParentId = (int)$wcKategorie['id'];
        }
    }

    private function baueProduktPayload(array $artikel, int $shopId): array
    {
        $preis = $this->repo->findEndkundenPreis((int)$artikel['artikel_id']);
        $wcKategorieIds = $this->repo->findWcKategorieIds((int)$artikel['artikel_id'], $shopId);

        $payload = [
            'name'              => $artikel['name'],
            'sku'               => $artikel['artikelnummer'],
            'description'       => $artikel['beschreibung'] ?? '',
            'short_description' => $artikel['kurzbeschreibung'] ?? '',
            'status'            => $artikel['aktiv'] ? 'publish' : 'draft',
        ];

        if ($preis !== null) {
            $payload['regular_price'] = number_format($preis, 2, '.', '');
        }

        if (!empty($wcKategorieIds)) {
            $payload['categories'] = array_map(fn($id) => ['id' => (int)$id], $wcKategorieIds);
        }

        // Vater mit Achsen -> WooCommerce "Variable Product". syncAchsenFuerVater()
        // lief bereits weiter oben in der Aufrufkette, die Zuweisungen existieren
        // also schon (sonst wäre dort bereits eine Exception geflogen).
        $attribute = [];
        $achsen = $this->repo->findAchsenFuerArtikel((int)$artikel['artikel_id']);
        if (!empty($achsen)) {
            $payload['type'] = 'variable';
            $attribute = array_map(function (array $achse) use ($artikel, $shopId) {
                $zuweisung = $this->repo->findAchseShopZuweisung((int)$achse['achse_id'], $shopId);
                $werte = $this->repo->findWerteFuerAchse((int)$artikel['artikel_id'], (int)$achse['achse_id']);
                return [
                    'id'        => (int)$zuweisung['externe_attribut_id'],
                    'variation' => true,
                    'visible'   => true,
                    'options'   => array_column($werte, 'wert'),
                ];
            }, $achsen);
        }

        // Hersteller-Filter (kein Variations-Attribut, `variation => false`) --
        // syncHerstellerFuerArtikel() lief für diese Zeile bereits weiter oben,
        // die Zuweisung existiert also schon. Rein additiv, ändert `type` nicht.
        if (!empty($artikel['hersteller_id'])) {
            $herstellerZuweisung = $this->repo->findHerstellerShopZuweisung((int)$artikel['hersteller_id'], $shopId);
            $attribute[] = [
                'id'        => (int)$herstellerZuweisung['externe_attribut_id'],
                'variation' => false,
                'visible'   => true,
                'options'   => [$this->repo->findHerstellerName((int)$artikel['hersteller_id'])],
            ];
        }

        if (!empty($attribute)) {
            $payload['attributes'] = $attribute;
        }

        // Bestand NUR bei Standalone-Artikeln setzen -- bei einem Variable
        // Product (Achsen vorhanden) verwaltet WooCommerce Bestand pro
        // Variation, nicht am Elternprodukt (siehe baueVariationPayload).
        if (empty($achsen)) {
            $payload += $this->baueBestandsFelder((int)$artikel['artikel_id']);
        }

        // Bilder: ALLE Bilder des Artikels als 'images'-Array (Plural!), in
        // Positions-Reihenfolge (Hauptbild zuerst). syncBilderFuerArtikel()
        // lief für diese Zeile bereits weiter oben in der Aufrufkette.
        $images = $this->sammleBildReferenzen((int)$artikel['artikel_id'], $shopId);
        if (!empty($images)) {
            $payload['images'] = $images;
        }

        return $payload;
    }

    /** WC-Bild-Referenzen (nur bereits erfolgreich synced) eines Artikels, in Positions-Reihenfolge. */
    private function sammleBildReferenzen(int $artikelId, int $shopId): array
    {
        $referenzen = [];
        foreach ($this->bilderRepo->findByArtikelId($artikelId) as $bild) {
            $zuweisung = $this->repo->findBildShopZuweisung((int)$bild['id'], $shopId);
            if ($zuweisung && $zuweisung['external_id']) {
                $referenzen[] = ['id' => (int)$zuweisung['external_id']];
            }
        }
        return $referenzen;
    }

    /** Payload für eine WooCommerce-Variation (= unser Kind-Artikel). */
    private function baueVariationPayload(array $kind, int $shopId): array
    {
        $preis = $this->repo->findEndkundenPreis((int)$kind['artikel_id']);

        $payload = ['sku' => $kind['artikelnummer']];
        if ($preis !== null) {
            $payload['regular_price'] = number_format($preis, 2, '.', '');
        }

        // Bei Variationen heißt das Feld 'option' (Singular, ein fester Wert) --
        // beim Eltern-Payload oben ist es 'options' (Plural, alle möglichen Werte).
        $payload['attributes'] = array_map(function (array $wert) use ($shopId) {
            $achseZuweisung = $this->repo->findAchseShopZuweisung((int)$wert['achse_id'], $shopId);
            return [
                'id'     => (int)$achseZuweisung['externe_attribut_id'],
                'option' => $wert['wert'],
            ];
        }, $this->repo->findKombinationFuerKind((int)$kind['artikel_id']));

        // Bei Variationen heißt das Bild-Feld 'image' (Singular, EIN Bild) --
        // beim Eltern-Payload ist es 'images' (Plural, ganze Galerie). Nur das
        // Hauptbild (Position 0, erstes Element -- BilderRepository sortiert
        // bereits danach) ergibt hier Sinn, WooCommerce zeigt pro Variation
        // ohnehin nur ein einziges Bild.
        $bilder = $this->sammleBildReferenzen((int)$kind['artikel_id'], $shopId);
        if (!empty($bilder)) {
            $payload['image'] = $bilder[0];
        }

        // Jedes Kind ist ein eigener artikel-Datensatz mit eigenem Bestand --
        // hat_eigenen_lagerstand (Kind bucht auf Vater-Bestand) ist zwar in
        // der DB vorgesehen, aber nirgends im System tatsächlich verdrahtet
        // (siehe project_shop_sync.md), darum hier bewusst nicht extra beachtet.
        $payload += $this->baueBestandsFelder((int)$kind['artikel_id']);

        return $payload;
    }

    /**
     * Bestandsfelder für ein WooCommerce-Produkt/eine Variation. Leeres Array
     * bei `hat_lagerstand=0` (z.B. Download-Artikel) -- Artikel bleibt dann
     * einfach immer kaufbar, kein Bestandsfeld im Payload nötig.
     */
    private function baueBestandsFelder(int $artikelId): array
    {
        $info = $this->repo->findBestandInfo($artikelId);
        if (!$info['hat_lagerstand']) {
            return [];
        }

        $verfuegbar = max(0, (int)$info['gesamtbestand'] - (int)$info['reserviert']);

        return [
            'manage_stock' => true,
            'stock_quantity' => $verfuegbar,
            'backorders' => $info['ueberverkauf_erlaubt'] ? 'notify' : 'no',
        ];
    }

    /**
     * Stellt sicher, dass alle Achsen eines Vater-Artikels (+ ihre Werte) als
     * globales WooCommerce-Attribut (+ Terms) existieren. "Erst nachsehen, dann
     * anlegen" statt Try/Catch auf einen Duplikat-Fehler, weil WooCommerce bei
     * einem doppelten Attribut-Namen anders als bei Terms KEINE ID des bereits
     * bestehenden Attributs zurückliefert (siehe WooCommerceClient::listeAttribute()).
     * Läuft für JEDEN fälligen Artikel (Vater wie Kind), ist aber pro Achse/Wert
     * durch die lokale Zuweisungstabelle idempotent -- nur beim allerersten Mal
     * wird tatsächlich mit der WooCommerce-API gesprochen.
     */
    private function syncAchsenFuerVater(WooCommerceClient $client, int $vaterId, int $shopId): void
    {
        foreach ($this->repo->findAchsenFuerArtikel($vaterId) as $achse) {
            $achseId = (int)$achse['achse_id'];
            $zuweisung = $this->repo->findAchseShopZuweisung($achseId, $shopId);

            if ($zuweisung && $zuweisung['externe_attribut_id']) {
                $attributId = (int)$zuweisung['externe_attribut_id'];
            } else {
                $attributId = $this->findeOderErstelleAttribut($client, $achse['name']);
                $this->repo->upsertAchseZuweisung($achseId, $shopId, (string)$attributId);
            }

            $this->syncWerteFuerAchse($client, $vaterId, $achseId, $attributId, $shopId);
        }
    }

    private function findeOderErstelleAttribut(WooCommerceClient $client, string $name, array $extraFelder = []): int
    {
        foreach ($client->listeAttribute() as $wcAttribut) {
            if (mb_strtolower($wcAttribut['name']) === mb_strtolower($name)) {
                return (int)$wcAttribut['id'];
            }
        }
        return (int)$client->erstelleAttribut(['name' => $name] + $extraFelder)['id'];
    }

    /**
     * Hersteller-Filter (Entscheidung siehe project_hersteller_shop_filter.md):
     * EIN globales "Hersteller"-Attribut mit `has_archives` (WooCommerce baut
     * daraus automatisch eine Übersichts-/Einzelseite je Hersteller, ohne
     * eigenen Code) -- ein Term darunter pro konkretem Hersteller. Unabhängig
     * vom bestehenden Hersteller-Kategorie-Ast, der als reine Vor-Filter-
     * Gruppierung für Kunden bestehen bleibt (Jackys Entscheidung 2026-07-21).
     */
    private function syncHerstellerFuerArtikel(WooCommerceClient $client, int $herstellerId, int $shopId): void
    {
        $zuweisung = $this->repo->findHerstellerShopZuweisung($herstellerId, $shopId);
        if ($zuweisung && $zuweisung['externe_attribut_id'] && $zuweisung['externe_term_id']) {
            return;
        }

        $attributId = $this->repo->findHerstellerAttributIdFuerShop($shopId)
            ?? (string)$this->findeOderErstelleAttribut($client, 'Hersteller', ['has_archives' => true]);

        $herstellerName = $this->repo->findHerstellerName($herstellerId);
        $termId = null;
        foreach ($client->listeAttributTerms((int)$attributId) as $wcTerm) {
            if (mb_strtolower($wcTerm['name']) === mb_strtolower($herstellerName)) {
                $termId = (int)$wcTerm['id'];
                break;
            }
        }
        $termId ??= (int)$client->erstelleAttributTerm((int)$attributId, ['name' => $herstellerName])['id'];

        $this->repo->upsertHerstellerZuweisung($herstellerId, $shopId, $attributId, (string)$termId);
    }

    private function syncWerteFuerAchse(WooCommerceClient $client, int $vaterId, int $achseId, int $attributId, int $shopId): void
    {
        $offeneWerte = [];
        foreach ($this->repo->findWerteFuerAchse($vaterId, $achseId) as $wert) {
            $zuweisung = $this->repo->findWertShopZuweisung((int)$wert['wert_id'], $shopId);
            if (!$zuweisung || !$zuweisung['externe_term_id']) {
                $offeneWerte[] = $wert;
            }
        }
        if (empty($offeneWerte)) {
            return;
        }

        // Terms des Attributs nur EINMAL laden (nicht pro Wert) -- billiger und
        // deckt auch den Fall ab, dass der Term schon manuell/durch einen anderen
        // Vater-Artikel mit dem gleichen Wertenamen existiert.
        $vorhandeneTerms = [];
        foreach ($client->listeAttributTerms($attributId) as $wcTerm) {
            $vorhandeneTerms[mb_strtolower($wcTerm['name'])] = (int)$wcTerm['id'];
        }

        foreach ($offeneWerte as $wert) {
            $key = mb_strtolower($wert['wert']);
            $termId = $vorhandeneTerms[$key]
                ?? (int)$client->erstelleAttributTerm($attributId, ['name' => $wert['wert']])['id'];
            $this->repo->upsertWertZuweisung((int)$wert['wert_id'], $shopId, (string)$termId);
        }
    }

    /**
     * Lädt noch nicht synced Bilder eines Artikels in die WordPress-Mediathek
     * hoch. Ein Fehler bei EINEM Bild (z.B. Datei fehlt lokal, kein
     * Application-Password hinterlegt) blockiert nicht den ganzen Artikel --
     * gleiches Isolationsprinzip wie beim Kategorie-Vorlauf in syncShop().
     * Ohne erfolgreich hochgeladene Bilder synct der Artikel selbst einfach
     * ohne 'images'/'image' im Payload weiter.
     */
    private function syncBilderFuerArtikel(WooCommerceClient $client, int $artikelId, int $shopId): void
    {
        foreach ($this->bilderRepo->findByArtikelId($artikelId) as $bild) {
            $bildId = (int)$bild['id'];
            $zuweisung = $this->repo->findBildShopZuweisung($bildId, $shopId);
            if ($zuweisung && $zuweisung['sync_status'] === 'synced' && $zuweisung['external_id']) {
                continue;
            }

            try {
                $pfad = __DIR__ . '/../../../public/uploads/artikel/' . $artikelId . '/' . $bild['dateiname'];
                $wcMedia = $client->ladeBildHoch($pfad, $bild['dateiname'], $bild['alt_text'] ?? '');
                $this->repo->markiereBildSynced($bildId, $shopId, (string)$wcMedia['id']);
            } catch (Throwable $e) {
                $this->repo->markiereBildFehler($bildId, $shopId, $e->getMessage());
                Logger::log('shop.bild_sync_fehler', 'artikel_bilder', $bildId, [
                    'artikel_id' => $artikelId,
                    'fehler' => $e->getMessage(),
                ], $this->jarvisId, 'error');
            }
        }
    }

    /**
     * Einmaliger Bulk-Modus für die Erstbefüllung (siehe project_shop_sync.md):
     * statt jedes Bild einzeln per Byte-Upload hochzuladen (viel zu langsam bei
     * tausenden Artikeln über die VPN-Leitung), hat Jacky die Bilder VORHER per
     * FTP direkt auf den WordPress-Server kopiert -- gleiche Ordnerstruktur wie
     * public/uploads/artikel/{artikel_id}/{dateiname}, nur unter $bilderBasisUrl.
     * WooCommerce sideloaded das Bild dann von der EIGENEN Domain (schnell,
     * kein Byte-Transfer über unsere Leitung).
     *
     * Voraussetzung: der Artikel muss in WooCommerce schon existieren (der
     * normale Text-Sync über syncShop() muss also vorher gelaufen sein) --
     * findArtikelMitOffenenBildernUndExternalId() liefert von vornherein nur
     * Artikel mit gesetzter external_id, nichts weiter zu prüfen hier.
     *
     * Nicht Teil des normalen Crons -- läuft in einer eigenen Schleife mit
     * echter ID-Cursor-Pagination (kein LIMIT/OFFSET-"Stecken bleiben" wie bei
     * findFaelligeArtikel(), das für den laufenden 15-Minuten-Cron mit
     * kleinem Batch gedacht ist, nicht für einen einmaligen Gesamtdurchlauf
     * über tausende Artikel).
     *
     * @return array{erfolg:int,fehler:int}
     */
    public function erstbefuellungBilderPerUrl(array $shop, string $bilderBasisUrl, int $batchGroesse = 200): array
    {
        $client = new WooCommerceClient(
            $shop['wc_url'],
            $shop['wc_key'],
            $shop['wc_secret'],
            $shop['wp_username'],
            $shop['wp_app_password']
        );
        $erfolg = 0;
        $fehler = 0;
        $letzteArtikelId = 0;

        while (true) {
            $rows = $this->repo->findArtikelMitOffenenBildernUndExternalId((int)$shop['id'], $letzteArtikelId, $batchGroesse);
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $letzteArtikelId = max($letzteArtikelId, (int)$row['artikel_id']);

                $offeneBilder = array_values(array_filter(
                    $this->bilderRepo->findByArtikelId((int)$row['artikel_id']),
                    function (array $bild) use ($shop) {
                        $zuweisung = $this->repo->findBildShopZuweisung((int)$bild['id'], (int)$shop['id']);
                        return !($zuweisung && $zuweisung['sync_status'] === 'synced' && $zuweisung['external_id']);
                    }
                ));
                if (empty($offeneBilder)) {
                    continue;
                }

                $istKind = $row['vaterartikel_id'] !== null;
                // Variation zeigt nur EIN Bild (Position 0, gleiche Konvention wie
                // baueVariationPayload()) -- bei einem Kind reicht das erste offene Bild.
                $zuVerarbeiten = $istKind ? [$offeneBilder[0]] : $offeneBilder;

                try {
                    $bildUrls = array_map(function (array $bild) use ($row, $bilderBasisUrl) {
                        return [
                            'src' => rtrim($bilderBasisUrl, '/') . '/artikel/' . $row['artikel_id'] . '/' . $bild['dateiname'],
                            'alt' => $bild['alt_text'] ?? '',
                        ];
                    }, $zuVerarbeiten);

                    if ($istKind) {
                        $wcObjekt = $client->aktualisiereVariation((string)$row['vater_external_id'], (string)$row['external_id'], ['image' => $bildUrls[0]]);
                        $zurueckgegeben = [$wcObjekt['image']];
                    } else {
                        $wcObjekt = $client->aktualisiereProdukt((string)$row['external_id'], ['images' => $bildUrls]);
                        $zurueckgegeben = $wcObjekt['images'];
                    }

                    // WooCommerce liefert die Bilder in derselben Reihenfolge zurück,
                    // in der sie geschickt wurden -- Zuordnung per Index zurück zu
                    // unseren artikel_bilder-Zeilen möglich.
                    foreach ($zuVerarbeiten as $i => $bild) {
                        if (isset($zurueckgegeben[$i]['id'])) {
                            $this->repo->markiereBildSynced((int)$bild['id'], (int)$shop['id'], (string)$zurueckgegeben[$i]['id']);
                            $erfolg++;
                        }
                    }
                } catch (Throwable $e) {
                    foreach ($zuVerarbeiten as $bild) {
                        $this->repo->markiereBildFehler((int)$bild['id'], (int)$shop['id'], $e->getMessage());
                    }
                    Logger::log('shop.bild_erstbefuellung_fehler', 'artikel', (int)$row['artikel_id'], [
                        'shop'   => $shop['slug'],
                        'fehler' => $e->getMessage(),
                    ], $this->jarvisId, 'error');
                    $fehler++;
                }
            }
        }

        return ['erfolg' => $erfolg, 'fehler' => $fehler];
    }
}
