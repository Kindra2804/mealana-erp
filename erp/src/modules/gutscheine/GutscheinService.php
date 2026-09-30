<?php

require_once __DIR__ . '/GutscheinRepository.php';
require_once __DIR__ . '/../shop/WooCommerceClient.php';
require_once __DIR__ . '/../kunden/KundenRepository.php';
require_once __DIR__ . '/../dokumente/DokumentService.php';
require_once __DIR__ . '/../../core/logger.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Mailer.php';

/**
 * GutscheinService – Geschäftslogik für Gutscheine.
 *
 * ERP ist Source of Truth (Betrag/Restguthaben/Status). WooCommerce bekommt bei
 * jeder Wert-Änderung nur einen aktualisierten fixed_cart-Coupon gespiegelt --
 * nie umgekehrt. Siehe project_gutscheine.md für den vollen Plan (drei
 * Entstehungswege: Kasse, ERP-manuell, Shop-Gutschein-Artikel -- alle laufen
 * hier zusammen).
 *
 * usage_limit=1 am WC-Coupon ist die Absicherung gegen Doppel-Einlösung im
 * Zeitfenster zwischen Einlösung und dem naechsten (poll-basierten)
 * Bestellungs-Sync -- eine Teileinlösung bekommt deshalb IMMER einen neuen
 * Code für den Rest, nie denselben Code mit reduziertem Betrag weiterlaufen.
 */
class GutscheinService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // ohne 0/O/1/I/L (Verwechslungsgefahr)
    private const CODE_GRUPPEN  = 3;
    private const CODE_LAENGE_PRO_GRUPPE = 4;

    private GutscheinRepository $repo;
    private PDO $db;
    private int $jarvisId;

    public function __construct()
    {
        $this->repo = new GutscheinRepository();
        $this->db   = Database::getInstance();
        $this->jarvisId = (int)$this->db
            ->query("SELECT id FROM benutzer WHERE username = 'system'")
            ->fetchColumn();
    }

    /**
     * Erzeugt einen neuen Gutschein (alle drei Entstehungswege laufen hier zusammen).
     * $daten: betrag, vorlage_id?, kunden_id?, empfaenger_name?, empfaenger_email?,
     *         zustellung_am?, versandart?, grusstext?, shop_id?, kanal_erstellt,
     *         auftrag_id_ursprung?
     */
    public function erstelleGutschein(array $daten, int $benutzerId): array
    {
        $betrag = round((float)($daten['betrag'] ?? 0), 2);
        if ($betrag <= 0) {
            return ['erfolg' => false, 'fehler' => ['Betrag muss größer als 0 sein.']];
        }

        $gueltigkeitTage = (int)($this->ladeEinstellung('gutschein_gueltigkeit_tage') ?? 3650);
        $gueltigBis = $daten['gueltig_bis'] ?? date('Y-m-d', strtotime("+{$gueltigkeitTage} days"));

        $code = $this->generiereEindeutigenCode();

        $id = $this->repo->insert([
            'code'                => $code,
            'vorlage_id'          => !empty($daten['vorlage_id']) ? (int)$daten['vorlage_id'] : null,
            'betrag'              => $betrag,
            'restguthaben'        => $betrag,
            'gueltig_bis'         => $gueltigBis,
            'status'              => 'aktiv',
            'kunden_id'           => !empty($daten['kunden_id']) ? (int)$daten['kunden_id'] : null,
            'empfaenger_name'     => $daten['empfaenger_name']  ?? null,
            'empfaenger_email'    => $daten['empfaenger_email'] ?? null,
            'zustellung_am'       => $daten['zustellung_am']    ?? null,
            'versandart'          => $daten['versandart']       ?? 'selbst_ausdrucken',
            'grusstext'           => $daten['grusstext']        ?? null,
            'shop_id'             => !empty($daten['shop_id']) ? (int)$daten['shop_id'] : null,
            'kanal_erstellt'      => $daten['kanal_erstellt']   ?? 'manuell',
            'auftrag_id_ursprung' => !empty($daten['auftrag_id_ursprung']) ? (int)$daten['auftrag_id_ursprung'] : null,
            'vorgaenger_gutschein_id' => !empty($daten['vorgaenger_gutschein_id']) ? (int)$daten['vorgaenger_gutschein_id'] : null,
            'ausgestellt_von'     => $benutzerId,
        ]);

        $this->repo->insertTransaktion([
            'gutschein_id'  => $id,
            'auftrag_id'    => !empty($daten['auftrag_id_ursprung']) ? (int)$daten['auftrag_id_ursprung'] : null,
            'kassen_bon_id' => !empty($daten['kassen_bon_id']) ? (int)$daten['kassen_bon_id'] : null,
            'betrag'        => $betrag,
            'kanal'         => $this->kanalFuerTransaktion($daten['kanal_erstellt'] ?? 'manuell'),
            'notiz'         => 'Gutschein ausgestellt',
            'benutzer_id'   => $benutzerId,
        ]);

        Logger::log('gutschein.erstellt', 'gutscheine', $id, [
            'code' => $code, 'betrag' => $betrag, 'kanal' => $daten['kanal_erstellt'] ?? 'manuell',
        ], $benutzerId);

        // Shop-Spiegel NICHT direkt hier (hätte die Kasse bei langsamer/fehlender
        // Internetverbindung blockiert) -- nur als fällig markieren, der Shop-Sync-Cron
        // legt den Coupon an (syncShopCoupons()). Der Code funktioniert an der Kasse sofort.
        $this->markiereShopSync($id);

        return ['erfolg' => true, 'id' => $id, 'code' => $code];
    }

    /** Jede Wertänderung (Ausstellung, Einlösung, Storno) -> Coupon im Shop beim nächsten Cron-Lauf anpassen. */
    public function markiereShopSync(int $gutscheinId): void
    {
        $this->db->prepare("UPDATE gutscheine SET woo_sync_faellig = 1 WHERE id = ?")->execute([$gutscheinId]);
    }

    /** Shop, in dem ein Gutschein online einlösbar ist: eigene shop_id, sonst Einstellung gutschein_shop_id. */
    public function shopIdFuer(array $gutschein): ?int
    {
        if (!empty($gutschein['shop_id'])) return (int)$gutschein['shop_id'];
        $id = (int)($this->ladeEinstellung('gutschein_shop_id') ?? 0);
        return $id > 0 ? $id : null;
    }

    /**
     * Cron (cron/shop_sync.php): gleicht alle fälligen Gutscheine dieses Shops mit
     * WooCommerce ab. Einlösbar -> Coupon anlegen/Betrag aktualisieren; nicht (mehr)
     * einlösbar (eingelöst, Rest auf neuen Code übertragen, storniert, abgelaufen) ->
     * Coupon löschen, damit er online nicht mehr verwendet werden kann.
     *
     * @return array{angelegt:int, aktualisiert:int, geloescht:int, fehler:int}
     */
    public function syncShopCoupons(array $shop): array
    {
        $zahl = ['angelegt' => 0, 'aktualisiert' => 0, 'geloescht' => 0, 'fehler' => 0];
        $client = new WooCommerceClient($shop['wc_url'], $shop['wc_key'], $shop['wc_secret']);
        $standardShop = (int)($this->ladeEinstellung('gutschein_shop_id') ?? 0);

        $stmt = $this->db->prepare("
            SELECT id FROM gutscheine
            WHERE woo_sync_faellig = 1
              AND (shop_id = :shop OR (shop_id IS NULL AND :standard = :shop2))
            ORDER BY id
            LIMIT 200
        ");
        $stmt->execute(['shop' => (int)$shop['id'], 'standard' => $standardShop, 'shop2' => (int)$shop['id']]);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            try {
                $ergebnis = $this->spiegleZuWooCommerce((int)$id, $client);
                $zahl[$ergebnis]++;
                $this->db->prepare("UPDATE gutscheine SET woo_sync_faellig = 0 WHERE id = ?")->execute([(int)$id]);
            } catch (Throwable $e) {
                $zahl['fehler']++;
                Logger::log('gutschein.wc_spiegel_fehlgeschlagen', 'gutscheine', (int)$id, [
                    'shop' => $shop['slug'], 'fehler' => $e->getMessage(),
                ], $this->jarvisId, 'warn');
            }
        }
        return $zahl;
    }

    /**
     * Legt den Coupon in WooCommerce an, aktualisiert ihn oder löscht ihn (siehe
     * syncShopCoupons()). Gespiegelt wird als Germanized-"Wertgutschein":
     * is_voucher=yes -> Einlösung als Zahlungsmittel NACH Steuer (Mehrzweckgutschein,
     * Ware bleibt voll versteuert); free_shipping=true bedeutet bei Germanized-Wert-
     * gutscheinen "deckt auch Versandkosten" (NICHT Gratisversand) -- beides am
     * 2026-09-30 per Store-API gegen indra-design.at verifiziert. usage_limit=1: siehe
     * Klassenkommentar (Teileinlösung erzeugt immer einen neuen Code).
     *
     * @return string 'angelegt'|'aktualisiert'|'geloescht'
     */
    public function spiegleZuWooCommerce(int $gutscheinId, WooCommerceClient $client): string
    {
        $gutschein = $this->repo->findById($gutscheinId);
        if (!$gutschein) {
            throw new RuntimeException("Gutschein $gutscheinId nicht gefunden.");
        }

        $einloesbar = $gutschein['status'] === 'aktiv'
            && (float)$gutschein['restguthaben'] > 0
            && ($gutschein['gueltig_bis'] === null || $gutschein['gueltig_bis'] >= date('Y-m-d'));

        if (!$einloesbar) {
            if (!empty($gutschein['woo_coupon_id'])) {
                try {
                    $client->loescheCoupon((int)$gutschein['woo_coupon_id']);
                } catch (WooCommerceNotFoundException $e) {
                    // schon weg (z.B. im wp-admin gelöscht) -- Ziel erreicht
                }
                $this->repo->updateWooCouponId($gutscheinId, null);
            }
            return 'geloescht';
        }

        $payload = [
            'code'           => $gutschein['code'],
            'discount_type'  => 'fixed_cart',
            'amount'         => number_format((float)$gutschein['restguthaben'], 2, '.', ''),
            'usage_limit'    => 1,
            'individual_use' => false,
            'free_shipping'  => true,
            'description'    => 'MeaLana Gutschein ' . $gutschein['code'] . ' (vom ERP verwaltet — nicht hier ändern)',
            'date_expires'   => $gutschein['gueltig_bis'],
            'meta_data'      => [['key' => 'is_voucher', 'value' => 'yes']],
        ];

        if (!empty($gutschein['woo_coupon_id'])) {
            try {
                $client->aktualisiereCoupon((int)$gutschein['woo_coupon_id'], $payload);
                return 'aktualisiert';
            } catch (WooCommerceNotFoundException $e) {
                $this->repo->updateWooCouponId($gutscheinId, null); // im Shop gelöscht -> neu anlegen
            }
        }

        // Code existiert im Shop evtl. schon (z.B. Anlage lief, Antwort ging verloren)
        $vorhanden = $client->sucheCouponNachCode($gutschein['code']);
        if ($vorhanden) {
            $client->aktualisiereCoupon((int)$vorhanden['id'], $payload);
            $this->repo->updateWooCouponId($gutscheinId, (int)$vorhanden['id']);
            return 'aktualisiert';
        }

        $ergebnis = $client->erstelleCoupon($payload);
        if (!empty($ergebnis['id'])) {
            $this->repo->updateWooCouponId($gutscheinId, (int)$ergebnis['id']);
        }
        return 'angelegt';
    }

    /**
     * Bucht eine Einlösung/Teileinlösung. Bei Restbetrag > 0 wird IMMER ein neuer
     * Code erzeugt (siehe Klassenkommentar) -- der alte WC-Coupon ist durch
     * usage_limit=1 ohnehin schon tot, unabhängig von diesem Aufruf.
     *
     * @return array{erfolg:bool, neuer_code:?string, fehler?:string[]}
     */
    public function einloesen(
        string $code,
        float $betrag,
        string $kanal,
        ?int $auftragId = null,
        ?int $kassenBonId = null,
        ?int $benutzerId = null
    ): array {
        $pruefung = $this->pruefeEinloesbar($code);
        if (!$pruefung['erfolg']) {
            return $pruefung;
        }
        $gutschein = $pruefung['gutschein'];

        $betrag = round(min($betrag, (float)$gutschein['restguthaben']), 2);
        if ($betrag <= 0) {
            return ['erfolg' => false, 'fehler' => ['Kein Restguthaben mehr auf diesem Gutschein.']];
        }

        $benutzerId ??= $this->jarvisId;
        $restZumUebertragen = round((float)$gutschein['restguthaben'] - $betrag, 2);
        $neuerStatus = $restZumUebertragen > 0 ? 'teilweise' : 'eingeloest';

        // WICHTIG: restguthaben geht hier IMMER auf 0 -- ein evtl. verbleibender
        // Rest wird sofort auf einen neuen Code übertragen (siehe unten), steht
        // also nie mehr auf DIESEM Code zur Verfügung. Ohne diesen Fix hätte der
        // alte (längst tote, usage_limit=1) Code fälschlich weiter ein
        // "Restguthaben" angezeigt -- genau die Verwirrung, die beim
        // Support-Anruf "mein Code funktioniert nicht" für Chaos sorgen würde.
        $this->repo->updateRestguthabenUndStatus((int)$gutschein['id'], 0.0, $neuerStatus);
        $this->markiereShopSync((int)$gutschein['id']); // alter Code darf online nicht mehr gelten
        $this->repo->insertTransaktion([
            'gutschein_id'  => $gutschein['id'],
            'auftrag_id'    => $auftragId,
            'kassen_bon_id' => $kassenBonId,
            'betrag'        => -$betrag,
            'kanal'         => $kanal,
            'notiz'         => 'Einlösung',
            'benutzer_id'   => $benutzerId,
        ]);
        Logger::log('gutschein.eingeloest', 'gutscheine', (int)$gutschein['id'], [
            'betrag' => $betrag, 'rest_uebertragen' => $restZumUebertragen, 'kanal' => $kanal,
        ], $benutzerId);

        $neuerCode = null;
        if ($restZumUebertragen > 0) {
            $neu = $this->erstelleGutschein([
                'betrag'           => $restZumUebertragen,
                'vorlage_id'       => $gutschein['vorlage_id'],
                'kunden_id'        => $gutschein['kunden_id'],
                'empfaenger_name'  => $gutschein['empfaenger_name'],
                'empfaenger_email' => $gutschein['empfaenger_email'],
                'versandart'       => $gutschein['empfaenger_email'] ? 'versenden' : 'selbst_ausdrucken',
                'shop_id'          => $gutschein['shop_id'],
                'kanal_erstellt'   => 'erp',
                'gueltig_bis'      => $gutschein['gueltig_bis'], // Restguthaben erbt die ursprüngliche Gültigkeit, kein Reset
                'vorgaenger_gutschein_id' => $gutschein['id'],
            ], $benutzerId);
            $neuerCode = $neu['code'] ?? null;

            // Auf der ALTEN Transaktionshistorie sichtbar hinterlegen, welcher
            // Code der Nachfolger ist -- direkt in der Liste sichtbar, ohne erst
            // der Kette folgen zu müssen (siehe auch findKette()).
            if ($neuerCode) {
                $this->repo->insertTransaktion([
                    'gutschein_id'  => $gutschein['id'],
                    'auftrag_id'    => $auftragId,
                    'kassen_bon_id' => $kassenBonId,
                    'betrag'        => 0,
                    'kanal'         => $kanal,
                    'notiz'         => "Restguthaben {$restZumUebertragen}€ übertragen auf neuen Code {$neuerCode}",
                    'benutzer_id'   => $benutzerId,
                ]);
            }
        }

        return ['erfolg' => true, 'neuer_code' => $neuerCode, 'restguthaben' => $restZumUebertragen];
    }

    /**
     * Erzeugt das PDF und verschickt es per Mail -- Empfänger hängt von
     * versandart ab: "versenden" -> an empfaenger_email (mit Käufername im
     * Text, falls kunden_id bekannt), "selbst_ausdrucken" -> an den Käufer
     * selbst (kunden_id), kein Versand an den Empfänger. Ohne verfügbare
     * E-Mail-Adresse (z.B. rein manuell erstellter Gutschein ohne Kunde) wird
     * nur das PDF erzeugt, kein Fehler -- Kasse übergibt dann direkt ausgedruckt.
     */
    /**
     * @param ?string $ersatzEmail Mailziel, wenn weder Empfänger (bei "versenden") noch ein
     *        Käufer-Kundendatensatz mit E-Mail bekannt ist -- z.B. Rechnungs-E-Mail einer
     *        Shop-Bestellung (Gast-Kauf "selbst ausdrucken", Restcode nach Online-Teileinlösung).
     */
    public function versende(int $gutscheinId, ?string $ersatzEmail = null): array
    {
        $gutschein = $this->repo->findById($gutscheinId);
        if (!$gutschein) {
            return ['erfolg' => false, 'fehler' => ['Gutschein nicht gefunden.']];
        }

        $kaeufer = null;
        if (!empty($gutschein['kunden_id'])) {
            $kundenRepo = new KundenRepository();
            $kaeufer = $kundenRepo->findById((int)$gutschein['kunden_id']) ?: null;
        }

        if ($gutschein['versandart'] === 'versenden' && !empty($gutschein['empfaenger_email'])) {
            $zielEmail = $gutschein['empfaenger_email'];
            $kaeuferName = $kaeufer ? trim(($kaeufer['vorname'] ?? '') . ' ' . ($kaeufer['nachname'] ?? '')) : null;
        } elseif ($kaeufer && !empty($kaeufer['email'])) {
            $zielEmail = $kaeufer['email'];
            $kaeuferName = null; // an sich selbst -- kein "X hat dir geschenkt"-Text nötig
        } elseif ($ersatzEmail !== null && filter_var($ersatzEmail, FILTER_VALIDATE_EMAIL)) {
            $zielEmail = $ersatzEmail;
            $kaeuferName = null;
        } else {
            return ['erfolg' => true, 'versendet' => false]; // kein Mailziel bekannt, kein Fehler
        }

        $dokumentService = new DokumentService();
        $pdfPfad = $dokumentService->erstelleGutscheinPdf($gutscheinId);

        $mailer = new Mailer();
        $mailer->sendeTemplate(
            $zielEmail,
            'Dein Gutschein von MEALANA',
            'mails/gutschein_versand.html.twig',
            [
                'empfaenger_name' => $gutschein['empfaenger_name'],
                'kaeufer_name'    => $kaeuferName,
                'betrag'          => $gutschein['betrag'],
                'code'            => $gutschein['code'],
                'gueltig_bis'     => $gutschein['gueltig_bis'] ? date('d.m.Y', strtotime($gutschein['gueltig_bis'])) : '',
                'grusstext'       => $gutschein['grusstext'],
            ],
            [['pfad' => $pdfPfad, 'name' => basename($pdfPfad)]]
        );

        $this->repo->markiereVersendet($gutscheinId);
        Logger::log('gutschein.versendet', 'gutscheine', $gutscheinId, ['email' => $zielEmail], $this->jarvisId);

        return ['erfolg' => true, 'versendet' => true];
    }

    /**
     * Prüft, ob ein Code aktuell einlösbar ist (Kasse vor dem Bezahlen, und
     * einloesen() selbst). Bei einem durch Teileinlösung ersetzten Code wird der
     * aktuell gültige Nachfolger-Code mitgeliefert -- genau der Support-Fall
     * "mein Code funktioniert nicht" (Kunde hat die Mail mit dem neuen Code übersehen).
     *
     * @return array{erfolg:bool, gutschein?:array, fehler?:string[], nachfolger?:array}
     */
    public function pruefeEinloesbar(string $code): array
    {
        $gutschein = $this->findeCodeTolerant($code);
        if (!$gutschein) {
            return ['erfolg' => false, 'fehler' => ['Gutschein-Code nicht gefunden.']];
        }
        if ($gutschein['status'] === 'storniert') {
            return ['erfolg' => false, 'fehler' => ['Dieser Gutschein wurde storniert.']];
        }
        if ($gutschein['status'] === 'teilweise' || $gutschein['status'] === 'eingeloest') {
            $kette = $this->repo->findKette((int)$gutschein['id']);
            $aktuell = end($kette);
            if ($aktuell && (int)$aktuell['id'] !== (int)$gutschein['id']
                && in_array($aktuell['status'], ['aktiv', 'teilweise'], true)) {
                return [
                    'erfolg' => false,
                    'fehler' => ['Dieser Code wurde bereits eingelöst — das Restguthaben liegt auf dem neuen Code '
                        . $aktuell['code'] . ' (€ ' . number_format((float)$aktuell['restguthaben'], 2, ',', '.') . ').'],
                    'nachfolger' => ['code' => $aktuell['code'], 'restguthaben' => (float)$aktuell['restguthaben']],
                ];
            }
            return ['erfolg' => false, 'fehler' => ['Dieser Gutschein wurde bereits vollständig eingelöst.']];
        }
        if ($gutschein['status'] === 'abgelaufen') {
            return ['erfolg' => false, 'fehler' => ['Dieser Gutschein ist abgelaufen.']];
        }
        if ($gutschein['gueltig_bis'] !== null && $gutschein['gueltig_bis'] < date('Y-m-d')) {
            $this->repo->updateRestguthabenUndStatus((int)$gutschein['id'], (float)$gutschein['restguthaben'], 'abgelaufen');
            $this->markiereShopSync((int)$gutschein['id']);
            return ['erfolg' => false, 'fehler' => ['Dieser Gutschein ist abgelaufen.']];
        }
        if ((float)$gutschein['restguthaben'] <= 0) {
            return ['erfolg' => false, 'fehler' => ['Kein Restguthaben mehr auf diesem Gutschein.']];
        }
        return ['erfolg' => true, 'gutschein' => $gutschein];
    }

    /**
     * Code-Suche, die Scanner-Tippfehler verzeiht: Barcode-Scanner mit US-Tastaturbelegung
     * an einem deutschen PC tippen "ß" statt "-" und vertauschen Y/Z (EANs sind reine
     * Ziffern, da fällt das nie auf -- Gutschein-Codes enthalten aber Y, Z und "-").
     */
    public function findeCodeTolerant(string $code): array|false
    {
        $code = strtoupper(str_replace(['ß', 'ẞ', '?'], '-', trim($code)));
        $g = $this->repo->findByCode($code);
        if (!$g && strpbrk($code, 'YZ') !== false) {
            $g = $this->repo->findByCode(strtr($code, ['Y' => 'Z', 'Z' => 'Y']));
        }
        return $g;
    }

    /**
     * Gegenbuchung, wenn ein Kassenbon storniert wird, auf dem Gutscheine
     * VERKAUFT/AUSGESTELLT oder damit BEZAHLT wurden:
     * - ausgestellte Gutscheine werden storniert, solange sie unangetastet sind;
     *   schon (teil)eingelöste -> Warnung, manuell klären
     * - Einlösungen: der eingelöste Betrag kommt als NEUER Code zurück (der alte
     *   ist per usage_limit=1 im Shop ohnehin tot, siehe Klassenkommentar)
     * Alle Gegenbuchungen hängen am Storno-Bon, nicht am Originalbon.
     *
     * @return array{warnungen:string[], neue_codes:array}
     */
    public function bonStorniert(int $bonId, int $stornoBonId, string $stornoBonNr, int $benutzerId): array
    {
        $warnungen = [];
        $neueCodes = [];

        $stmt = $this->db->prepare("
            SELECT t.gutschein_id, t.betrag, g.code, g.betrag AS g_betrag, g.restguthaben, g.status,
                   g.kunden_id, g.vorlage_id, g.empfaenger_name, g.empfaenger_email, g.shop_id, g.gueltig_bis
            FROM gutschein_transaktionen t
            JOIN gutscheine g ON g.id = t.gutschein_id
            WHERE t.kassen_bon_id = :bon AND t.betrag <> 0
        ");
        $stmt->execute(['bon' => $bonId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $betrag = (float)$t['betrag'];
            if ($betrag > 0) {
                $unangetastet = $t['status'] === 'aktiv'
                    && abs((float)$t['restguthaben'] - (float)$t['g_betrag']) < 0.005;
                if ($unangetastet) {
                    $this->repo->storniere((int)$t['gutschein_id']);
                    $this->markiereShopSync((int)$t['gutschein_id']);
                    $this->repo->insertTransaktion([
                        'gutschein_id'  => $t['gutschein_id'],
                        'auftrag_id'    => null,
                        'kassen_bon_id' => $stornoBonId,
                        'betrag'        => -(float)$t['restguthaben'],
                        'kanal'         => 'kasse',
                        'notiz'         => 'Storniert mit Storno-Bon ' . $stornoBonNr,
                        'benutzer_id'   => $benutzerId,
                    ]);
                    Logger::log('gutschein.storniert', 'gutscheine', (int)$t['gutschein_id'], ['bon_nr' => $stornoBonNr], $benutzerId);
                } else {
                    $warnungen[] = 'Gutschein ' . $t['code'] . ' wurde bereits (teil)eingelöst und konnte nicht automatisch storniert werden — bitte manuell klären.';
                    Logger::log('gutschein.storno_nicht_moeglich', 'gutscheine', (int)$t['gutschein_id'], ['bon_nr' => $stornoBonNr], $benutzerId, 'warn');
                }
            } else {
                $neu = $this->erstelleGutschein([
                    'betrag'           => abs($betrag),
                    'vorlage_id'       => $t['vorlage_id'],
                    'kunden_id'        => $t['kunden_id'],
                    'empfaenger_name'  => $t['empfaenger_name'],
                    'empfaenger_email' => $t['empfaenger_email'],
                    'shop_id'          => $t['shop_id'],
                    'kanal_erstellt'   => 'kasse',
                    'kassen_bon_id'    => $stornoBonId,
                    'gueltig_bis'      => $t['gueltig_bis'],
                    'vorgaenger_gutschein_id' => $t['gutschein_id'],
                ], $benutzerId);
                if ($neu['erfolg']) {
                    $neueCodes[] = ['id' => $neu['id'], 'code' => $neu['code'], 'betrag' => abs($betrag)];
                }
            }
        }

        return ['warnungen' => $warnungen, 'neue_codes' => $neueCodes];
    }

    public function generiereEindeutigenCode(): string
    {
        do {
            $gruppen = [];
            for ($g = 0; $g < self::CODE_GRUPPEN; $g++) {
                $gruppe = '';
                for ($i = 0; $i < self::CODE_LAENGE_PRO_GRUPPE; $i++) {
                    $gruppe .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
                }
                $gruppen[] = $gruppe;
            }
            $code = 'MEA-' . implode('-', $gruppen);
        } while ($this->repo->codeExistiert($code));

        return $code;
    }

    private function kanalFuerTransaktion(string $kanalErstellt): string
    {
        return match ($kanalErstellt) {
            'kasse'       => 'kasse',
            'woocommerce' => 'woocommerce',
            default       => 'erp',
        };
    }

    private function ladeEinstellung(string $schluessel): ?string
    {
        $stmt = $this->db->prepare("SELECT wert FROM system_einstellungen WHERE schluessel = :s");
        $stmt->execute(['s' => $schluessel]);
        $wert = $stmt->fetchColumn();
        return $wert !== false ? $wert : null;
    }
}
