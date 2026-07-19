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

        foreach ($this->repo->findFaelligeArtikel((int)$shop['id']) as $row) {
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
