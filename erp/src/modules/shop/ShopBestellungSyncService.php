<?php

require_once __DIR__ . '/ShopSyncRepository.php';
require_once __DIR__ . '/WooCommerceClient.php';
require_once __DIR__ . '/../../core/logger.php';
require_once __DIR__ . '/../auftraege/AuftragRepository.php';
require_once __DIR__ . '/../auftraege/AuftragService.php';
require_once __DIR__ . '/../kunden/KundenRepository.php';
require_once __DIR__ . '/../kunden/KundenService.php';
require_once __DIR__ . '/../konfigurator/KonfiguratorService.php';
require_once __DIR__ . '/../gutscheine/GutscheinRepository.php';
require_once __DIR__ . '/../gutscheine/GutscheinService.php';
require_once __DIR__ . '/../dokumente/DokumentService.php';
require_once __DIR__ . '/../dokumente/RechnungMailService.php';
require_once __DIR__ . '/../auftraege/AuftragAbschluss.php';

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
        // completed aus dem Shop heißt nicht "im ERP abgeschlossen" — versendet+verrechnet wird hier entschieden
        'completed'  => ['bezahlt', 'in_bearbeitung'],
        'cancelled'  => ['storniert', 'storniert'],
        'refunded'   => ['erstattet', null],
    ];

    private ShopSyncRepository $repo;
    private AuftragRepository $auftragRepo;
    private AuftragService $auftragService;
    private KundenRepository $kundenRepo;
    private KundenService $kundenService;
    private KonfiguratorService $konfiguratorService;
    private GutscheinRepository $gutscheinRepo;
    private GutscheinService $gutscheinService;
    private int $jarvisId;

    public function __construct()
    {
        $this->repo = new ShopSyncRepository();
        $this->auftragRepo = new AuftragRepository();
        $this->auftragService = new AuftragService();
        $this->kundenRepo = new KundenRepository();
        $this->kundenService = new KundenService();
        $this->konfiguratorService = new KonfiguratorService();
        $this->gutscheinRepo = new GutscheinRepository();
        $this->gutscheinService = new GutscheinService();
        $this->jarvisId = (int)Database::getInstance()
            ->query("SELECT id FROM benutzer WHERE username = 'system'")
            ->fetchColumn();
    }

    /**
     * Gegenrichtung ERP → Shop: eine im ERP gebuchte Zahlung (Zahlung buchen, z.B.
     * Überweisung laut Kontoauszug) an die WooCommerce-Bestellung melden -- sonst bliebe
     * sie im Shop für immer "In Wartestellung" und Folgeprozesse (Gutschein-Erzeugung,
     * Kundenkonto-Status) liefen nie an. Den Status setzt meldeStatusAnShop() nach der
     * gemeinsamen Regel (bezahlt → In Bearbeitung, bezahlt+versendet → Fertiggestellt);
     * eine Teilzahlung bekommt nur die Notiz. Danach sofortiger Bestellungs-Abgleich,
     * damit z.B. ein gekaufter Gutschein gleich entsteht statt erst beim nächsten Cron-Lauf.
     *
     * @return array{gemeldet:bool, hinweis:string}
     */
    public function meldeZahlungAnShop(int $auftragId, float $betrag, string $buchungsdatum, ?string $notiz, string $zahlungsartLabel, bool $vollstaendigBezahlt, float $offen): array
    {
        $auftrag = $this->auftragRepo->findById($auftragId);
        if (!$auftrag || ($auftrag['kanal'] ?? '') !== 'woocommerce' || empty($auftrag['kanal_auftrag_id'])) {
            return ['gemeldet' => false, 'hinweis' => ''];
        }
        $shop = $this->findeShop((int)$auftrag['shop_id']);
        if (!$shop) {
            return ['gemeldet' => false, 'hinweis' => 'Shop ohne WooCommerce-Anbindung — Zahlung nur im ERP gebucht.'];
        }

        $text = sprintf('%s: %s € am %s per %s (gebucht im ERP, %s)%s',
            $vollstaendigBezahlt ? 'Zahlung eingegangen' : 'Teilzahlung eingegangen',
            number_format($betrag, 2, ',', '.'),
            date('d.m.Y', strtotime($buchungsdatum)),
            $zahlungsartLabel,
            $auftrag['auftrag_nr'],
            ($vollstaendigBezahlt ? '' : ' — noch offen: ' . number_format($offen, 2, ',', '.') . ' €')
                . ($notiz ? ' — Notiz: ' . $notiz : '')
        );

        // Kunde sieht im Kundenkonto nur Betrag/Datum/Zahlungsart -- Auftragsnummer und
        // Buchungsvermerk (z.B. "testbuchung…") bleiben in der internen Notiz
        $kundenText = sprintf('%s: %s € am %s per %s.%s',
            $vollstaendigBezahlt ? 'Zahlung eingegangen' : 'Teilzahlung eingegangen',
            number_format($betrag, 2, ',', '.'),
            date('d.m.Y', strtotime($buchungsdatum)),
            $zahlungsartLabel,
            $vollstaendigBezahlt ? ' Vielen Dank!' : ' Noch offen: ' . number_format($offen, 2, ',', '.') . ' €.'
        );

        $ergebnis = $this->meldeStatusAnShop($auftragId, $shop, $text, $kundenText);
        if (!$ergebnis['gemeldet']) {
            return ['gemeldet' => false, 'hinweis' => 'Zahlung gebucht, aber der Shop konnte nicht aktualisiert werden (' . $ergebnis['fehler'] . ') — bitte die Bestellung im Shop von Hand auf "In Bearbeitung" setzen.'];
        }

        // Sofort-Abgleich: holt die eben geänderte Bestellung zurück (Gutschein-Kauf etc.)
        try {
            $this->syncBestellungen($shop);
        } catch (Throwable $e) {
            // unkritisch -- der 15-Minuten-Cron holt es nach
        }
        return ['gemeldet' => true, 'hinweis' => ''];
    }

    /**
     * Soll-Status der Shop-Bestellung aus dem ERP-Stand. Bewusst NIE "Fertiggestellt"
     * für unbezahlte Aufträge: der Abgleich Shop → ERP liest processing/completed als
     * "bezahlt" -- eine versendete Rechnungs-/Nachnahme-Bestellung würde sonst im ERP
     * fälschlich bezahlt. Versendet + unbezahlt → nur Notiz ('versand_notiz').
     */
    private function sollShopStatus(array $auftrag): ?string
    {
        $versendet = in_array($auftrag['lieferstatus'], ['versendet', 'abgeschlossen', 'retoure_offen'], true);
        if ($auftrag['zahlungsstatus'] === 'bezahlt') {
            return $versendet ? 'completed' : 'processing';
        }
        return $versendet ? 'versand_notiz' : null;
    }

    /**
     * Unsichtbare Endung (Zero-Width-Space) an allen ERP-Kunden-Notizen. Das Snippet "MeaLana:
     * ERP-Zahlung ohne Shop-Mail" erkennt daran ERP-Notizen und schickt keine WC-Hinweis-Mail --
     * von Hand im wp-admin geschriebene Kunden-Notizen verschicken ihre Mail weiterhin.
     */
    public const KUNDENNOTIZ_MARKE = "​";

    /** Rangfolge, damit nie zurückgestuft wird (completed → processing o.ä.). */
    private const SHOP_STATUS_RANG = ['versand_notiz' => 1, 'processing' => 2, 'completed' => 3];

    /**
     * Meldet den ERP-Stand einer Shop-Bestellung an WooCommerce, falls sich der Soll-Status
     * vom zuletzt bekannten (auftraege.shop_status_gemeldet) unterscheidet -- mit Notiz
     * (Zahlungs-/Versanddaten aus dem ERP) und Meta-Markierung, damit das Shop-Snippet
     * "MeaLana: ERP-Zahlung ohne Shop-Mail" die doppelte WooCommerce-Mail unterdrückt
     * (das ERP verschickt Zahlungseingang/Versandbestätigung/Abholbereit selbst).
     * $interneNotiz (nur wp-admin, z.B. mit Auftragsnummer + Buchungsvermerk) und $kundenNotiz
     * (im Kundenkonto sichtbar) werden auch ohne Statuswechsel angehängt. Kunden-Notizen tragen
     * die unsichtbare Markierung KUNDENNOTIZ_MARKE -- das Snippet unterdrückt für GENAU diese die
     * WC-Mail "Hinweis zu Ihrer Bestellung" (das ERP hat den Kunden schon per eigener Mail informiert).
     *
     * @return array{gemeldet:bool, ziel:?string, fehler:?string}
     */
    public function meldeStatusAnShop(int $auftragId, array $shop, ?string $interneNotiz = null, ?string $kundenNotiz = null): array
    {
        $auftrag = $this->auftragRepo->findById($auftragId);
        if (!$auftrag || empty($auftrag['kanal_auftrag_id'])) {
            return ['gemeldet' => false, 'ziel' => null, 'fehler' => 'keine Shop-Bestellung'];
        }
        $orderId = (int)$auftrag['kanal_auftrag_id'];
        $ziel    = $this->sollShopStatus($auftrag);
        $bekannt = $auftrag['shop_status_gemeldet'] ?? null;
        $neu     = $ziel !== null && (self::SHOP_STATUS_RANG[$ziel] ?? 0) > (self::SHOP_STATUS_RANG[$bekannt] ?? 0);

        if (!$neu && $interneNotiz === null && $kundenNotiz === null) {
            return ['gemeldet' => false, 'ziel' => $ziel, 'fehler' => null]; // nichts zu tun
        }

        $client = new WooCommerceClient($shop['wc_url'], $shop['wc_key'], $shop['wc_secret'], $shop['wp_username'], $shop['wp_app_password']);
        try {
            if ($neu && $ziel !== 'versand_notiz') {
                $meta = [['key' => '_mealana_erp_zahlung', 'value' => date('Y-m-d')]];
                if ($ziel === 'completed') $meta[] = ['key' => '_mealana_erp_versand', 'value' => date('Y-m-d')];
                $client->aktualisiereBestellung($orderId, ['status' => $ziel, 'meta_data' => $meta]);
            }
            if ($kundenNotiz !== null) {
                $client->erstelleBestellNotiz($orderId, $kundenNotiz . self::KUNDENNOTIZ_MARKE, true);
            }
            if ($interneNotiz !== null) {
                $client->erstelleBestellNotiz($orderId, $interneNotiz);
            }
            if ($neu && in_array($ziel, ['completed', 'versand_notiz'], true)) {
                $client->erstelleBestellNotiz($orderId, $this->versandNotiz($auftrag) . self::KUNDENNOTIZ_MARKE, true);
            }
        } catch (Throwable $e) {
            Logger::log('shop.status_melden_fehler', 'auftraege', $auftragId, [
                'wc_order_id' => $orderId, 'ziel' => $ziel, 'fehler' => $e->getMessage(),
            ], $this->jarvisId, 'error');
            return ['gemeldet' => false, 'ziel' => $ziel, 'fehler' => $e->getMessage()];
        }

        if ($neu) {
            Database::getInstance()->prepare("UPDATE auftraege SET shop_status_gemeldet = ? WHERE id = ?")->execute([$ziel, $auftragId]);
        }
        Logger::log('shop.status_gemeldet', 'auftraege', $auftragId, ['wc_order_id' => $orderId, 'ziel' => $ziel], $this->jarvisId);
        return ['gemeldet' => true, 'ziel' => $ziel, 'fehler' => null];
    }

    /** Kunden-Notiz zum Versand/Abschluss mit den Daten aus dem ERP (im Kundenkonto sichtbar). */
    private function versandNotiz(array $auftrag): string
    {
        $datum = !empty($auftrag['versand_datum']) ? date('d.m.Y', strtotime($auftrag['versand_datum'])) : date('d.m.Y');
        if (!empty($auftrag['tracking_nr'])) {
            return 'Ihre Bestellung wurde am ' . $datum . ' versendet'
                . (!empty($auftrag['versanddienstleister']) ? ' mit ' . $auftrag['versanddienstleister'] : '')
                . ', Sendungsnummer ' . $auftrag['tracking_nr'] . '.';
        }
        if (($auftrag['lieferart'] ?? '') === 'abholung') {
            return 'Ihre Bestellung wurde am ' . date('d.m.Y') . ' abgeholt. Vielen Dank!';
        }
        return 'Ihre Bestellung wurde am ' . date('d.m.Y') . ' abgeschlossen.';
    }

    /**
     * Cron/Komplettabgleich: alle Shop-Aufträge dieses Shops melden, deren ERP-Stand im
     * Shop noch nicht angekommen ist (z.B. am Packplatz versendet, an der Kasse abgeholt,
     * reiner Gutschein-Auftrag abgeschlossen). Unabhängig davon, WO im ERP der Status
     * geändert wurde -- deshalb hier gesammelt statt an jeder einzelnen Stelle.
     *
     * @return array{gemeldet:int, fehler:int}
     */
    public function meldeOffeneStatusAnShop(array $shop): array
    {
        $stmt = Database::getInstance()->prepare("
            SELECT id FROM auftraege
            WHERE shop_id = ? AND kanal = 'woocommerce' AND kanal_auftrag_id IS NOT NULL
              AND lieferstatus <> 'storniert'
              AND (zahlungsstatus = 'bezahlt' OR lieferstatus IN ('versendet', 'abgeschlossen', 'retoure_offen'))
              AND COALESCE(shop_status_gemeldet, '') <> 'completed'
            ORDER BY id
            LIMIT 100
        ");
        $stmt->execute([(int)$shop['id']]);
        $zahl = ['gemeldet' => 0, 'fehler' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $r = $this->meldeStatusAnShop((int)$id, $shop);
            if ($r['gemeldet']) $zahl['gemeldet']++;
            elseif ($r['fehler'] !== null) $zahl['fehler']++;
        }
        return $zahl;
    }

    /** Tatsächlichen Shop-Status merken (nur höher, nie zurückstufen), damit der Cron nicht unnötig meldet. */
    private function merkeShopStatus(int $auftragId, string $wcStatus): void
    {
        if (!isset(self::SHOP_STATUS_RANG[$wcStatus])) return;
        $stmt = Database::getInstance()->prepare("SELECT shop_status_gemeldet FROM auftraege WHERE id = ?");
        $stmt->execute([$auftragId]);
        $bekannt = $stmt->fetchColumn() ?: null;
        if (self::SHOP_STATUS_RANG[$wcStatus] > (self::SHOP_STATUS_RANG[$bekannt] ?? 0)) {
            Database::getInstance()->prepare("UPDATE auftraege SET shop_status_gemeldet = ? WHERE id = ?")->execute([$wcStatus, $auftragId]);
        }
    }

    /** Rastet einen errechneten Satz (z.B. 20,03) auf den nächsten Satz aus steuerklassen ein (max. 0,5 %-Punkte daneben). */
    private function echterSteuersatz(float $roh): float
    {
        static $saetze = null;
        $saetze ??= array_map('floatval', Database::getInstance()->query("SELECT DISTINCT satz FROM steuerklassen")->fetchAll(PDO::FETCH_COLUMN));
        $best = null;
        foreach ($saetze as $s) {
            if ($best === null || abs($s - $roh) < abs($best - $roh)) $best = $s;
        }
        return ($best !== null && abs($best - $roh) <= 0.5) ? $best : round($roh, 2);
    }

    private function findeShop(int $shopId): ?array
    {
        foreach ($this->repo->findAktiveShops() as $s) {
            if ((int)$s['id'] === $shopId) return $s;
        }
        return null;
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
            $this->aktualisiereBestehenden((int)$bestehender['id'], $bestehender, $zahlungsstatus, $lieferstatus, $order, $shopId);
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
            'lieferart'                 => $this->ermittleLieferart($order),
            // brutto (ERP führt Versandkosten immer brutto, siehe Versandsteuer) --
            // WooCommerce liefert Netto + Steuer getrennt
            'versandkosten'             => round((float)($order['shipping_total'] ?? 0) + (float)($order['shipping_tax'] ?? 0), 2),
            'notiz_versand'             => trim((string)($order['customer_note'] ?? '')) !== '' ? $order['customer_note'] : null,
        ];

        $ergebnis = $this->auftragService->anlegen($auftragData, $positionen, $this->jarvisId);
        if (!$ergebnis['erfolg']) {
            throw new RuntimeException('Auftrag anlegen fehlgeschlagen: ' . implode(', ', $ergebnis['fehler']));
        }
        $auftragId = (int)$ergebnis['id'];

        $this->setzeStatus($auftragId, $zahlungsstatus, $lieferstatus, 'Import aus WooCommerce');
        $this->merkeShopStatus($auftragId, (string)($order['status'] ?? ''));

        // Nur beim ERSTEN Import verarbeiten -- coupon_lines ändert sich nach
        // Bestellabschluss nicht mehr, ein erneuter Poll würde sonst doppelt buchen.
        $this->verarbeiteGutscheinEinloesungen($order, $auftragId);
        $this->verarbeiteGutscheinKauf($order, $auftragId, $shopId, $zahlungsstatus);
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
    private function aktualisiereBestehenden(
        int $auftragId,
        array $bestehender,
        string $zahlungsstatus,
        ?string $lieferstatus,
        array $order,
        int $shopId
    ): void {
        $neuerLieferstatus = $lieferstatus === 'storniert' ? 'storniert' : null;

        // Zahlungen werden inzwischen AUCH im ERP gebucht (Zahlung buchen → Shop-Meldung,
        // siehe meldeZahlungAnShop). Ein im ERP höherer Zahlungsstatus darf deshalb nicht
        // durch einen (noch) älteren Shop-Stand zurückgestuft werden. Storno/Erstattung
        // aus dem Shop gelten weiterhin immer.
        $rang = ['ausstehend' => 1, 'teilbezahlt' => 2, 'bezahlt' => 3];
        $erp  = (string)($bestehender['zahlungsstatus'] ?? '');
        if (isset($rang[$zahlungsstatus], $rang[$erp]) && $rang[$erp] > $rang[$zahlungsstatus]) {
            $zahlungsstatus = $erp;
        }

        $this->merkeShopStatus($auftragId, (string)($order['status'] ?? ''));
        $this->setzeStatus($auftragId, $zahlungsstatus, $neuerLieferstatus, 'Aktualisiert aus WooCommerce', $bestehender);

        // Zahlungsstatus kann erst bei einem SPÄTEREN Poll auf "bezahlt" wechseln
        // (z.B. Vorkasse) -- der Gutschein-Kauf-Check läuft deshalb bei JEDEM
        // Poll, ist aber über findByAuftragUrsprung() idempotent.
        $this->verarbeiteGutscheinKauf($order, $auftragId, $shopId, $zahlungsstatus);
    }

    /**
     * Erkennt eingelöste Gutschein-Coupons in einer Bestellung und bucht sie über
     * GutscheinService::einloesen() -- der tatsächlich abgezogene Betrag steht in
     * coupon_lines[].discount (kann kleiner als das Restguthaben sein, dann
     * entsteht dort automatisch ein neuer Code für den Rest, siehe
     * GutscheinService::einloesen()). Unbekannte Coupon-Codes (normale
     * Rabatt-Coupons, kein Gutschein) werden stillschweigend übersprungen.
     */
    private function verarbeiteGutscheinEinloesungen(array $order, int $auftragId): void
    {
        $billingEmail = trim((string)($order['billing']['email'] ?? '')) ?: null;

        foreach ($order['coupon_lines'] ?? [] as $couponLine) {
            $code = strtoupper(trim((string)($couponLine['code'] ?? '')));
            $gutschein = $code !== '' ? $this->gutscheinRepo->findByCode($code) : false;
            if (!$gutschein) {
                continue; // kein Gutschein-Code (z.B. normaler Rabatt-Coupon)
            }

            $betrag = $this->eingeloesterGutscheinBetrag($order, $couponLine, $code);
            if ($betrag <= 0) {
                continue;
            }

            $ergebnis = $this->gutscheinService->einloesen($code, $betrag, 'woocommerce', $auftragId);
            if (!$ergebnis['erfolg']) {
                Logger::log('gutschein.einloesung_fehler', 'auftraege', $auftragId, [
                    'code' => $code, 'fehler' => $ergebnis['fehler'] ?? [],
                ], $this->jarvisId, 'warn');
                continue;
            }

            // Gutschein ist ein Zahlungsmittel -> am Auftrag festhalten (Positionen bleiben
            // zum vollen Preis, Germanized zieht den Gutschein erst nach Steuer ab)
            Database::getInstance()->prepare("
                UPDATE auftraege SET gutschein_id = ?, gutschein_betrag = gutschein_betrag + ? WHERE id = ?
            ")->execute([(int)$gutschein['id'], $betrag, $auftragId]);

            if (!empty($ergebnis['neuer_code'])) {
                Logger::log('gutschein.teileinloesung_neuer_code', 'auftraege', $auftragId, [
                    'alter_code' => $code, 'neuer_code' => $ergebnis['neuer_code'],
                    'restguthaben' => $ergebnis['restguthaben'],
                ], $this->jarvisId);
                // Checkout-Hinweis verspricht es: Restguthaben kommt als neuer Code per Mail --
                // an den ursprünglichen Empfänger, sonst an die E-Mail dieser Bestellung
                $neu = $this->gutscheinRepo->findByCode($ergebnis['neuer_code']);
                if ($neu) {
                    try {
                        $this->gutscheinService->versende((int)$neu['id'], $billingEmail);
                    } catch (Throwable $e) {
                        Logger::log('gutschein.versand_fehler', 'gutscheine', (int)$neu['id'], [
                            'fehler' => $e->getMessage(),
                        ], $this->jarvisId, 'error');
                    }
                }
            }
        }
    }

    /**
     * Tatsächlich eingelöster Betrag eines Gutschein-Codes in einer Bestellung.
     * Gutscheine sind im Shop Germanized-"Wertgutscheine": der Coupon selbst rabattiert
     * NICHTS (discount = 0), Germanized zieht den Betrag nach Steuer als negative Gebühr
     * "Wertgutschein: <code>" ab (inkl. Versand). Fallback für alte/normale Coupons:
     * discount + discount_tax der Coupon-Zeile.
     */
    private function eingeloesterGutscheinBetrag(array $order, array $couponLine, string $code): float
    {
        $betrag = 0.0;
        foreach ($order['fee_lines'] ?? [] as $fee) {
            if (stripos((string)($fee['name'] ?? ''), $code) !== false) {
                $betrag += abs((float)($fee['total'] ?? 0) + (float)($fee['total_tax'] ?? 0));
            }
        }
        if ($betrag <= 0) {
            $betrag = (float)($couponLine['discount'] ?? 0) + (float)($couponLine['discount_tax'] ?? 0);
        }
        return round($betrag, 2);
    }

    /**
     * Erkennt den Kauf eines Shop-Gutschein-Artikels (artikel.ist_gutschein=1)
     * und erzeugt daraus einen echten Gutschein -- erst wenn zahlungsstatus
     * tatsächlich 'bezahlt' ist (nicht schon bei 'pending'/Vorkasse-Bestelleingang).
     * Personalisierung (Empfänger/Zustelldatum/Grußtext/Design) kommt aus dem
     * "_mealana_gutschein"-Line-Item-Meta (Checkout-Snippet, analog zu
     * "_mealana_konfig" beim Konfigurator).
     */
    private function verarbeiteGutscheinKauf(array $order, int $auftragId, int $shopId, string $zahlungsstatus): void
    {
        if ($zahlungsstatus !== 'bezahlt') {
            return;
        }

        $gutscheinArtikelIds = $this->gutscheinRepo->findeGutscheinArtikelIds();
        if (empty($gutscheinArtikelIds)) {
            return;
        }

        $billingEmail = trim((string)($order['billing']['email'] ?? '')) ?: null;
        $kundenStmt = Database::getInstance()->prepare("SELECT kunden_id FROM auftraege WHERE id = ?");
        $kundenStmt->execute([$auftragId]);
        $kundenId = (int)$kundenStmt->fetchColumn() ?: null;

        foreach ($order['line_items'] ?? [] as $item) {
            $sku = trim((string)($item['sku'] ?? ''));
            $artikelId = $sku !== '' ? $this->repo->findArtikelIdFuerSku($sku) : null;
            if ($artikelId === null || !in_array($artikelId, $gutscheinArtikelIds, true)) {
                continue;
            }

            // Idempotenz: pro WC-Line-Item-ID darf nur einmal ein Gutschein entstehen,
            // auch wenn dieselbe Bestellung (z.B. Menge > 1) mehrfach gepollt wird.
            if ($this->gutscheinRepo->existiertBereitsFuerLineItem($auftragId, (int)$item['id'])) {
                continue;
            }

            $meta = $this->leseGutscheinMetaAusLineItem($item);
            $menge = max(1, (int)($item['quantity'] ?? 1));
            $einzelBetrag = round((float)($item['total'] ?? 0) / $menge, 2);

            for ($i = 0; $i < $menge; $i++) {
                $ergebnis = $this->gutscheinService->erstelleGutschein([
                    'betrag'           => $meta['betrag'] ?? $einzelBetrag,
                    'vorlage_id'       => $meta['vorlage_id'] ?? null,
                    'empfaenger_name'  => $meta['empfaenger_name'] ?? null,
                    'empfaenger_email' => $meta['empfaenger_email'] ?? null,
                    'zustellung_am'    => $meta['zustellung_am'] ?? null,
                    'versandart'       => $meta['versandart'] ?? 'selbst_ausdrucken',
                    'grusstext'        => $meta['grusstext'] ?? null,
                    'kunden_id'        => $kundenId,
                    'shop_id'          => $shopId,
                    'kanal_erstellt'   => 'woocommerce',
                    'auftrag_id_ursprung' => $auftragId,
                ], $this->jarvisId);

                if ($ergebnis['erfolg']) {
                    $this->gutscheinRepo->verknuepfeMitLineItem((int)$ergebnis['id'], $auftragId, (int)$item['id']);
                    // Sofortversand, außer der Käufer hat ein späteres Zustelldatum gewählt
                    // (dann übernimmt der gutschein_versand-Cronjob).
                    if (empty($meta['zustellung_am'])) {
                        try {
                            $this->gutscheinService->versende((int)$ergebnis['id'], $billingEmail);
                        } catch (Throwable $e) {
                            Logger::log('gutschein.versand_fehler', 'gutscheine', (int)$ergebnis['id'], [
                                'fehler' => $e->getMessage(),
                            ], $this->jarvisId, 'error');
                        }
                    }
                } else {
                    Logger::log('gutschein.kauf_fehler', 'auftraege', $auftragId, [
                        'fehler' => $ergebnis['fehler'] ?? [],
                    ], $this->jarvisId, 'warn');
                }
            }
        }

        $this->schliesseGutscheinPositionenAb($auftragId, $gutscheinArtikelIds);
    }

    /**
     * Gutschein-Positionen gelten mit der Ausstellung als "geliefert" (Code + PDF per Mail,
     * nichts zu packen). Besteht der Auftrag nur aus Gutscheinen, ist er damit komplett
     * erledigt -- sonst hinge er für immer in Pickliste/Packplatz. Idempotent.
     */
    private function schliesseGutscheinPositionenAb(int $auftragId, array $gutscheinArtikelIds): void
    {
        $db = Database::getInstance();
        $ph = implode(',', array_fill(0, count($gutscheinArtikelIds), '?'));
        $db->prepare("
            UPDATE auftrag_positionen SET menge_geliefert = menge
            WHERE auftrag_id = ? AND artikel_id IN ($ph) AND COALESCE(menge_geliefert, 0) < menge
        ")->execute(array_merge([$auftragId], $gutscheinArtikelIds));

        $offen = $db->prepare("SELECT COUNT(*) FROM auftrag_positionen WHERE auftrag_id = ? AND menge - COALESCE(menge_geliefert, 0) > 0");
        $offen->execute([$auftragId]);
        $status = $db->prepare("SELECT lieferstatus FROM auftraege WHERE id = ?");
        $status->execute([$auftragId]);
        $lieferstatus = (string)$status->fetchColumn();

        if ((int)$offen->fetchColumn() === 0 && in_array($lieferstatus, ['neu', 'in_bearbeitung', 'versandbereit'], true)) {
            $this->auftragService->statusAktualisieren($auftragId, ['lieferstatus' => 'versendet'],
                'Nur Gutscheine — per E-Mail zugestellt, nichts zu versenden', $this->jarvisId);
            // Beleg: Rechnung über den Gutschein-Verkauf (0 %, Mehrzweckgutschein) — Jacky 2026-10-07.
            // Bei gemischten Bestellungen nimmt die Packplatz-Rechnung die Gutscheine automatisch mit.
            try {
                $re = (new DokumentService())->erstelleRechnung($auftragId, $this->jarvisId);
                if ($re['erfolg']) RechnungMailService::sende((int)$re['rechnung_id']);
            } catch (Throwable $e) {
                Logger::log('gutschein.rechnung_fehler', 'auftraege', $auftragId, ['fehler' => $e->getMessage()], $this->jarvisId, 'error');
            }
            AuftragAbschluss::pruefe($auftragId, $this->jarvisId);
        }
    }

    /**
     * Liest die Personalisierung aus dem "_mealana_gutschein"-Meta eines
     * Line-Items (JSON-Blob, vom Checkout-Snippet gesetzt -- analog zu
     * leseKonfigurationAusLineItem()). Fehlt das Meta (z.B. Snippet noch nicht
     * gebaut/aktiv), liefert diese Methode ein leeres Array -- der Gutschein
     * entsteht dann trotzdem, nur ohne Personalisierung.
     */
    private function leseGutscheinMetaAusLineItem(array $item): array
    {
        foreach ($item['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? '') !== '_mealana_gutschein') {
                continue;
            }
            $decoded = json_decode((string)($meta['value'] ?? ''), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
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
            $subtotalTax = (float)($item['subtotal_tax'] ?? $totalTax);
            // Aus gerundeten Beträgen errechnet ergibt z.B. 20,03 % -- auf den nächsten
            // echten Steuersatz einrasten, sonst findet der Buchhaltungsexport kein
            // USt-Konto und der Auftragsbetrag weicht um Cents ab (Fund 2026-09-30)
            $satz = $total > 0 ? $this->echterSteuersatz($totalTax / $total * 100) : ($totalTax > 0 ? 20.0 : 0.0);

            $konfig = $this->leseKonfigurationAusLineItem($item);

            // Frühwarnung bei veralteter Preis-Matrix im Shop: der Kunde hat trotzdem
            // den WC-Preis bezahlt (siehe Kommentar unten) -- hier wird NICHTS korrigiert,
            // nur geloggt, damit sowas auffällt bevor sich Beschwerden häufen.
            if (!empty($konfig['wert_ids']) && $konfig['preis_brutto'] !== null) {
                $berechnet = $this->konfiguratorService->berechnePreis($artikelId, $konfig['wert_ids']);
                if ($berechnet['erfolg'] && abs($berechnet['brutto'] - $konfig['preis_brutto']) > 0.01) {
                    Logger::log('shop.konfigurator_preis_abweichung', 'auftrag_positionen', 0, [
                        'artikel_id'        => $artikelId,
                        'shop_preis_brutto' => $konfig['preis_brutto'],
                        'erp_preis_brutto'  => $berechnet['brutto'],
                    ], $this->jarvisId, 'warn');
                }
            }

            $positionen[] = [
                'artikel_id'        => $artikelId,
                'bezeichnung'       => $item['name'] ?? '',
                'menge'             => $menge,
                // Preis 1:1 aus dem WC-Line-Item übernommen (nicht aus unseren
                // aktuellen artikel_preise nachgeschlagen) -- der Kunde hat
                // zum Bestellzeitpunkt einen bestimmten Preis bezahlt, der
                // muss eingefroren bleiben (passt zur "bezeichnung/ean
                // eingefroren"-Philosophie von auftrag_positionen).
                // Einzelpreis aus dem BRUTTO vor Rabatt (subtotal + subtotal_tax) -- WC liefert
                // nur centgerundete Netto-Zeilen (7,25 € → 6,04 netto → zurück 7,248 €). Vorher
                // kam der Preis aus dem rabattierten total UND rabatt_prozent wurde zusätzlich
                // gesetzt -> Coupon-Rabatt doppelt abgezogen (Fund 2026-09-30).
                'einzelpreis_netto' => $menge > 0 ? Positionsrechnung::einzelNettoAusBrutto(($subtotal + $subtotalTax) / $menge, $satz) : 0,
                'steuer_prozent'    => $satz,
                'rabatt_prozent'    => $subtotal > 0 ? max(0, round((1 - $total / $subtotal) * 100, 2)) : 0,
                'konfig_wert_ids'   => $konfig['wert_ids'],
                'konfig_freitext'   => !empty($konfig['klartext']) ? implode("\n", $konfig['klartext']) : null,
            ];
        }
        return $positionen;
    }

    /**
     * Liest die Konfigurator-Auswahl aus den meta_data eines WC-Line-Items.
     * Primärer Weg: Key "_mealana_konfig" mit JSON {version, werte:[wert_id,...], preis_brutto}
     * (Underscore-Präfix -- WooCommerce blendet ihn in Admin/Mails aus, siehe baueKonfiguratorFelder()
     * im Shop-Sync für das Gegenstück beim Senden). Fallback, falls das Shop-Frontend die
     * JSON-Variante mal verliert: Keys nach dem Muster "_mealana_konfig_achse_<id>" mit
     * numerischem Wert (wert_id).
     *
     * Bewusst rein/ohne DB-Zugriff -- per CLI mit handgebauten Arrays testbar, ohne WooCommerce
     * oder Datenbank zu brauchen.
     *
     * @return array{wert_ids: int[], preis_brutto: ?float}
     */
    public function leseKonfigurationAusLineItem(array $item): array
    {
        $metaData = $item['meta_data'] ?? [];

        foreach ($metaData as $meta) {
            if (($meta['key'] ?? '') !== '_mealana_konfig') continue;
            $decoded = json_decode((string)($meta['value'] ?? ''), true);
            if (is_array($decoded) && !empty($decoded['werte']) && is_array($decoded['werte'])) {
                return [
                    'wert_ids'     => array_map('intval', $decoded['werte']),
                    'preis_brutto' => isset($decoded['preis_brutto']) ? (float)$decoded['preis_brutto'] : null,
                    'klartext'     => $this->leseKlartextAusMetaData($metaData),
                ];
            }
        }

        // Fallback: einzelne _mealana_konfig_achse_<id>-Keys statt des JSON-Blobs
        $wertIds = [];
        foreach ($metaData as $meta) {
            $key = (string)($meta['key'] ?? '');
            if (preg_match('/^_mealana_konfig_achse_\d+$/', $key) && is_numeric($meta['value'] ?? null)) {
                $wertIds[] = (int)$meta['value'];
            }
        }
        return ['wert_ids' => $wertIds, 'preis_brutto' => null, 'klartext' => $this->leseKlartextAusMetaData($metaData)];
    }

    /**
     * Klartext-Fallback der Konfigurator-Auswahl, UNABHÄNGIG von jeder ID-Aufl��sung.
     * Snippet 34170 auf indra-design.at schreibt für jede gewählte Achse zusätzlich
     * zum technischen "_mealana_konfig"-JSON einen eigenen, nicht-Underscore-präfigierten
     * Meta-Key (Achsenname => Wert-Label, für Kunde/Admin in WooCommerce selbst gedacht) --
     * genau diese Klartext-Paare frieren wir hier zusätzlich ein. Grund: die IDs im JSON
     * beziehen sich auf die Preis-Matrix VOM SYNC-ZEITPUNKT DES PRODUKTS; werden
     * varianten_achse_werte danach bearbeitet/neu generiert (neue IDs), zeigen alte,
     * noch nicht neu synchte Matrizen ins Leere -- der Klartext bleibt davon unberührt
     * (Fund 2026-08-29: 5 von 7 Werten eines Testauftrags dadurch sonst spurlos verloren).
     */
    private function leseKlartextAusMetaData(array $metaData): array
    {
        $paare = [];
        foreach ($metaData as $meta) {
            $key   = (string)($meta['key'] ?? '');
            $value = $meta['value'] ?? '';
            if ($key === '' || $key[0] === '_' || !is_scalar($value) || trim((string)$value) === '') {
                continue;
            }
            $paare[] = $key . ': ' . $value;
        }
        return $paare;
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

    /**
     * "abholung" wenn eine der Versandpositionen die native WooCommerce-
     * Abholung ist (method_id "pickup_location", siehe Snippet 34171 auf
     * indra-design.at) -- sonst "versand". Gleiche Präfix-Liste wie dort,
     * für den Fall älterer/klassischer Local-Pickup-Varianten.
     */
    private function ermittleLieferart(array $order): string
    {
        $abholungPraefixe = ['pickup_location', 'local_pickup', 'legacy_local_pickup'];
        foreach ($order['shipping_lines'] ?? [] as $zeile) {
            $methodId = (string)($zeile['method_id'] ?? '');
            foreach ($abholungPraefixe as $praefix) {
                if (strpos($methodId, $praefix) === 0) {
                    return 'abholung';
                }
            }
        }
        return 'versand';
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
            $wcPaymentMethod === 'mealana_barabholung' => 'bar',
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
