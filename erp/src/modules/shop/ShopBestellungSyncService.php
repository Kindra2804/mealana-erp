<?php

require_once __DIR__ . '/ShopSyncRepository.php';
require_once __DIR__ . '/WooCommerceClient.php';
require_once __DIR__ . '/../../core/logger.php';
require_once __DIR__ . '/../auftraege/AuftragRepository.php';
require_once __DIR__ . '/../auftraege/AuftragService.php';
require_once __DIR__ . '/../kunden/KundenRepository.php';
require_once __DIR__ . '/../kunden/KundenService.php';

/**
 * ShopBestellungSyncService – Phase 3: Bestellungen aus WooCommerce ins ERP.
 *
 * Gegenrichtung zu ShopSyncService (der synct ERP→Shop). Reines Polling
 * (`modified_after`-Cursor in `shops.bestellungen_letzter_sync`) statt
 * Webhook -- unser ERP hat keinen öffentlichen Endpunkt, WooCommerce könnte
 * uns also ohnehin nicht per Push erreichen (siehe project_shop_sync.md).
 *
 * Idempotenz über `auftraege.shop_id` + `kanal_auftrag_id`: eine WC-Bestellung
 * erzeugt nie einen zweiten Auftrag, ein erneuter Poll aktualisiert nur den
 * Status. `AuftragService::anlegen()`/`statusAktualisieren()` bekommen die
 * Jarvis-ID explizit durchgereicht (kein `$_SESSION` im Cron-Kontext --
 * gleiches wiederkehrende Bug-Muster wie bei ShopSyncService/cron/mahnwesen).
 */
class ShopBestellungSyncService
{
    /** WC-Bestellstatus → [zahlungsstatus, lieferstatus]. null lieferstatus = unverändert lassen. */
    private const STATUS_MAP = [
        'pending'    => ['ausstehend', 'neu'],
        'on-hold'    => ['ausstehend', 'neu'],
        'processing' => ['bezahlt', 'in_bearbeitung'],
        'completed'  => ['bezahlt', 'abgeschlossen'],
        'cancelled'  => ['storniert', 'storniert'],
        'refunded'   => ['erstattet', null],
    ];

    private ShopSyncRepository $repo;
    private AuftragRepository $auftragRepo;
    private AuftragService $auftragService;
    private KundenRepository $kundenRepo;
    private KundenService $kundenService;
    private int $jarvisId;

    public function __construct()
    {
        $this->repo = new ShopSyncRepository();
        $this->auftragRepo = new AuftragRepository();
        $this->auftragService = new AuftragService();
        $this->kundenRepo = new KundenRepository();
        $this->kundenService = new KundenService();
        $this->jarvisId = (int)Database::getInstance()
            ->query("SELECT id FROM benutzer WHERE username = 'system'")
            ->fetchColumn();
    }

    /** @return array{erfolg:int,fehler:int} */
    public function syncBestellungen(array $shop): array
    {
        $client = new WooCommerceClient(
            $shop['wc_url'],
            $shop['wc_key'],
            $shop['wc_secret'],
            $shop['wp_username'],
            $shop['wp_app_password']
        );

        $bestellungen = $client->listeBestellungen($shop['bestellungen_letzter_sync']);
        $erfolg = 0;
        $fehler = 0;
        $letzterZeitpunkt = null;

        foreach ($bestellungen as $order) {
            $letzterZeitpunkt = $order['date_modified_gmt'] ?? $order['date_modified'] ?? $letzterZeitpunkt;
            try {
                $this->verarbeiteBestellung($order, (int)$shop['id']);
                $erfolg++;
            } catch (Throwable $e) {
                Logger::log('shop.bestellung_sync_fehler', 'auftraege', 0, [
                    'shop'        => $shop['slug'],
                    'wc_order_id' => $order['id'] ?? null,
                    'fehler'      => $e->getMessage(),
                ], $this->jarvisId, 'error');
                $fehler++;
            }
        }

        if ($letzterZeitpunkt !== null) {
            $this->repo->setzeBestellungenLetzterSync((int)$shop['id'], $letzterZeitpunkt);
        }

        return ['erfolg' => $erfolg, 'fehler' => $fehler];
    }

    private function verarbeiteBestellung(array $order, int $shopId): void
    {
        $mapping = self::STATUS_MAP[$order['status']] ?? null;
        if ($mapping === null) {
            // failed/checkout-draft/trash/unbekannt -- kein echter Auftrag
            return;
        }
        [$zahlungsstatus, $lieferstatus] = $mapping;

        $bestehender = $this->auftragRepo->findByShopUndKanalAuftragId($shopId, (int)$order['id']);
        if ($bestehender) {
            $this->aktualisiereBestehenden((int)$bestehender['id'], $bestehender, $zahlungsstatus, $lieferstatus);
            return;
        }

        $positionen = $this->bauePositionen($order);
        if (empty($positionen)) {
            return;
        }

        $auftragData = [
            'kunden_id'                 => $this->ermittleOderErstelleKunde($order, $shopId),
            'kunden_snapshot'           => $this->baueKundenSnapshot($order),
            'lieferadresse_snapshot'    => $this->baueAdresse($order['shipping'] ?? []),
            'rechnungsadresse_snapshot' => $this->baueAdresse($order['billing'] ?? []),
            'kanal'                     => 'woocommerce',
            'shop_id'                   => $shopId,
            'kanal_auftrag_id'          => (int)$order['id'],
            'zahlungsart'               => $this->mappeZahlungsart((string)($order['payment_method'] ?? '')),
            'lieferart'                 => 'versand',
            'versandkosten'             => (float)($order['shipping_total'] ?? 0),
        ];

        $ergebnis = $this->auftragService->anlegen($auftragData, $positionen, $this->jarvisId);
        if (!$ergebnis['erfolg']) {
            throw new RuntimeException('Auftrag anlegen fehlgeschlagen: ' . implode(', ', $ergebnis['fehler']));
        }

        $this->setzeStatus((int)$ergebnis['id'], $zahlungsstatus, $lieferstatus, 'Import aus WooCommerce');
    }

    /**
     * Phase 4 (eingegrenzt, siehe project_shop_sync.md): verknüpft eine
     * Bestellung mit einem echten `kunden`-Datensatz statt nur dem Snapshot.
     * Reihenfolge: 1) schon verknüpfte WC-Kunden-ID (schnellster, sicherster
     * Pfad) 2) exakter E-Mail-Hash-Match (Design aus project_kundendatenbank.md
     * -- bewusst KEIN Fuzzy-Match auf Name/Adresse, das bleibt manuelles
     * Merge-Queue-Thema für später) 3) neu anlegen. Gibt `null` zurück wenn
     * nichts davon klappt (z.B. Gast ohne Nachname/Firma) -- der Auftrag
     * bekommt dann trotzdem seinen `kunden_snapshot`, nur kein `kunden_id`.
     */
    private function ermittleOderErstelleKunde(array $order, int $shopId): ?int
    {
        $wcKundeId = (int)($order['customer_id'] ?? 0);

        if ($wcKundeId > 0) {
            $kundeId = $this->repo->findKundeIdFuerShopExternalId($shopId, (string)$wcKundeId);
            if ($kundeId !== null) {
                return $kundeId;
            }
        }

        $b = $order['billing'] ?? [];
        $email = trim((string)($b['email'] ?? ''));

        if ($email !== '') {
            $bestehender = $this->kundenRepo->findByEmailHash($email);
            if ($bestehender) {
                $kundeId = (int)$bestehender['id'];
                if ($wcKundeId > 0) {
                    $this->repo->upsertKundenShopZuweisung($kundeId, $shopId, (string)$wcKundeId);
                }
                return $kundeId;
            }
        }

        $ergebnis = $this->kundenService->anlegen([
            'vorname'              => $b['first_name'] ?? '',
            'nachname'             => $b['last_name'] ?? '',
            'firmenname'           => $b['company'] ?? '',
            'ist_firma'            => !empty($b['company']) ? 1 : 0,
            'email'                => $email !== '' ? $email : null,
            'telefon'              => $b['phone'] ?? '',
            'kundenherkunft'       => 'shop',
            'strasse'              => trim(($b['address_1'] ?? '') . ' ' . ($b['address_2'] ?? '')),
            'plz'                  => $b['postcode'] ?? '',
            'ort'                  => $b['city'] ?? '',
            'land'                 => $b['country'] ?? 'AT',
            // explizit null (nicht einfach weglassen) -- verschluesseln() nutzt
            // '?:' statt '??' und wirft sonst "Undefined array key"-Warnungen
            'kundengruppe_id'      => null,
            'zahlungsbedingung_id' => null,
            'standardzahlungsart'  => null,
            'kreditlimit'          => null,
        ], $this->jarvisId);

        if (!$ergebnis['erfolg']) {
            // Meist: weder Nachname noch Firma vorhanden (Pflichtfeld) --
            // Auftrag bekommt dann trotzdem seinen Snapshot, nur kein kunden_id.
            Logger::log('shop.kunde_anlegen_fehlgeschlagen', 'kunden', 0, [
                'email'  => $email,
                'fehler' => $ergebnis['fehler'],
            ], $this->jarvisId, 'warn');
            return null;
        }

        $kundeId = (int)$ergebnis['id'];
        if ($wcKundeId > 0) {
            $this->repo->upsertKundenShopZuweisung($kundeId, $shopId, (string)$wcKundeId);
        }
        return $kundeId;
    }

    /**
     * Update-Fall: zahlungsstatus wird IMMER nachgezogen (WC ist die Quelle
     * der Wahrheit für Zahlung). lieferstatus wird NUR bei 'storniert'
     * überschrieben -- der restliche Versand-Workflow (Packplatz, Tracking)
     * ist unser eigener interner Prozess und soll nicht zurückgesetzt werden.
     */
    private function aktualisiereBestehenden(int $auftragId, array $bestehender, string $zahlungsstatus, ?string $lieferstatus): void
    {
        $neuerLieferstatus = $lieferstatus === 'storniert' ? 'storniert' : null;
        $this->setzeStatus($auftragId, $zahlungsstatus, $neuerLieferstatus, 'Aktualisiert aus WooCommerce', $bestehender);
    }

    private function setzeStatus(int $auftragId, string $zahlungsstatus, ?string $lieferstatus, string $notiz, ?array $vorher = null): void
    {
        $felder = ['zahlungsstatus' => $zahlungsstatus];
        if ($lieferstatus !== null) {
            $felder['lieferstatus'] = $lieferstatus;
        }

        $this->auftragService->statusAktualisieren($auftragId, $felder, $notiz, $this->jarvisId);

        // statusAktualisieren() schließt Reservierungen selbst nur bei
        // versendet/abgeschlossen -- 'storniert' braucht das explizit hier.
        $warNichtSchonStorniert = ($vorher['lieferstatus'] ?? null) !== 'storniert';
        if (($felder['lieferstatus'] ?? null) === 'storniert' && $warNichtSchonStorniert) {
            $this->auftragRepo->schliesseReservierungen($auftragId);
        }
    }

    private function bauePositionen(array $order): array
    {
        $positionen = [];
        foreach ($order['line_items'] ?? [] as $item) {
            $menge = (int)($item['quantity'] ?? 0);
            if ($menge <= 0) {
                continue;
            }

            $sku = trim((string)($item['sku'] ?? ''));
            $artikelId = $sku !== '' ? $this->repo->findArtikelIdFuerSku($sku) : null;
            if ($artikelId === null) {
                Logger::log('shop.bestellung_sku_unbekannt', 'auftrag_positionen', 0, [
                    'sku'  => $sku,
                    'name' => $item['name'] ?? '',
                ], $this->jarvisId, 'warn');
                $artikelId = $this->repo->findDiversArtikelId();
                if ($artikelId === null) {
                    continue; // auch kein Divers-Platzhalter (99-9999) vorhanden
                }
            }

            $subtotal = (float)($item['subtotal'] ?? $item['total'] ?? 0);
            $total    = (float)($item['total'] ?? 0);
            $totalTax = (float)($item['total_tax'] ?? 0);

            $positionen[] = [
                'artikel_id'        => $artikelId,
                'bezeichnung'       => $item['name'] ?? '',
                'menge'             => $menge,
                // Preis 1:1 aus dem WC-Line-Item übernommen (nicht aus unseren
                // aktuellen artikel_preise nachgeschlagen) -- der Kunde hat
                // zum Bestellzeitpunkt einen bestimmten Preis bezahlt, der
                // muss eingefroren bleiben (passt zur "bezeichnung/ean
                // eingefroren"-Philosophie von auftrag_positionen).
                'einzelpreis_netto' => $menge > 0 ? round($total / $menge, 4) : 0,
                'steuer_prozent'    => $total > 0 ? round($totalTax / $total * 100, 2) : 20,
                'rabatt_prozent'    => $subtotal > 0 ? max(0, round((1 - $total / $subtotal) * 100, 2)) : 0,
            ];
        }
        return $positionen;
    }

    private function baueKundenSnapshot(array $order): array
    {
        $b = $order['billing'] ?? [];
        return [
            'name'    => trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? '')),
            'firma'   => $b['company'] ?? '',
            'strasse' => trim(($b['address_1'] ?? '') . ' ' . ($b['address_2'] ?? '')),
            'plz'     => $b['postcode'] ?? '',
            'ort'     => $b['city'] ?? '',
            'land'    => $b['country'] ?? '',
            'email'   => $b['email'] ?? '',
            'telefon' => $b['phone'] ?? '',
        ];
    }

    private function baueAdresse(array $adresse): array
    {
        return [
            'name'    => trim(($adresse['first_name'] ?? '') . ' ' . ($adresse['last_name'] ?? '')),
            'firma'   => $adresse['company'] ?? '',
            'strasse' => trim(($adresse['address_1'] ?? '') . ' ' . ($adresse['address_2'] ?? '')),
            'plz'     => $adresse['postcode'] ?? '',
            'ort'     => $adresse['city'] ?? '',
            'land'    => $adresse['country'] ?? '',
        ];
    }

    private function mappeZahlungsart(string $wcPaymentMethod): string
    {
        return match (true) {
            in_array($wcPaymentMethod, ['bacs', 'cheque'], true) => 'vorkasse',
            $wcPaymentMethod === 'cod' => 'nachnahme',
            in_array($wcPaymentMethod, ['paypal', 'ppcp-gateway', 'ppcp'], true) => 'paypal',
            default => $this->unbekannteZahlungsart($wcPaymentMethod),
        };
    }

    /** Unbekanntes Gateway (z.B. Kreditkarte/Stripe, aktuell nicht geplant) -> Fallback + Log statt Absturz. */
    private function unbekannteZahlungsart(string $wcPaymentMethod): string
    {
        if ($wcPaymentMethod !== '') {
            Logger::log('shop.unbekannte_zahlungsart', 'auftraege', 0, [
                'payment_method' => $wcPaymentMethod,
            ], $this->jarvisId, 'warn');
        }
        return 'vorkasse';
    }
}
