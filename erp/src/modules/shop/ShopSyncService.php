<?php

require_once __DIR__ . '/ShopSyncRepository.php';
require_once __DIR__ . '/WooCommerceClient.php';
require_once __DIR__ . '/../../core/logger.php';

/**
 * ShopSyncService – Phase 1: Standard-Artikel (kein Vater/Kind) nach WooCommerce pushen.
 *
 * Aufgerufen vom Sync-Cron, ein Durchlauf pro Shop. Jeder Artikel wird einzeln
 * verarbeitet -- ein Fehler bei einem Artikel darf die anderen nicht blockieren
 * (daher try/catch pro Artikel, nicht um die ganze Schleife).
 */
class ShopSyncService
{
    private ShopSyncRepository $repo;
    private int $jarvisId;

    public function __construct()
    {
        $this->repo = new ShopSyncRepository();
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
        $client = new WooCommerceClient($shop['wc_url'], $shop['wc_key'], $shop['wc_secret']);
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

        foreach ($faelligeArtikel as $row) {
            try {
                $payload = $this->baueProduktPayload($row, (int)$shop['id']);

                if ($row['external_id']) {
                    $wcProdukt = $client->aktualisiereProdukt($row['external_id'], $payload);
                } else {
                    $wcProdukt = $client->erstelleProdukt($payload);
                }

                $this->repo->markiereSynced((int)$row['artikel_shop_id'], (string)$wcProdukt['id']);
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
                $wcParentId = (int)$vorhandeneZuweisung['externe_kategorie_id'];
                continue;
            }
            $wcKategorie = $client->erstelleKategorie(['name' => $kategorie['name'], 'parent' => $wcParentId]);
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

        return $payload;
    }
}
