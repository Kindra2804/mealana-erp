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

        // WC-Spiegelung ist best-effort -- ein Netzwerkfehler darf die eigentliche
        // Gutschein-Erstellung nicht verhindern, der Code funktioniert in der Kasse
        // in jedem Fall sofort. Manueller Retry über spiegleZuWooCommerce() möglich.
        if (!empty($daten['shop_id'])) {
            try {
                $this->spiegleZuWooCommerce($id, (int)$daten['shop_id']);
            } catch (Throwable $e) {
                Logger::log('gutschein.wc_spiegel_fehlgeschlagen', 'gutscheine', $id, [
                    'fehler' => $e->getMessage(),
                ], $benutzerId, 'warn');
            }
        }

        return ['erfolg' => true, 'id' => $id, 'code' => $code];
    }

    /** Legt den Coupon in WooCommerce an ODER aktualisiert ihn (z.B. nach Teileinlösung). */
    public function spiegleZuWooCommerce(int $gutscheinId, int $shopId): void
    {
        $gutschein = $this->repo->findById($gutscheinId);
        if (!$gutschein) {
            throw new RuntimeException("Gutschein $gutscheinId nicht gefunden.");
        }

        $shop = $this->db->prepare("SELECT * FROM shops WHERE id = :id");
        $shop->execute(['id' => $shopId]);
        $shop = $shop->fetch(PDO::FETCH_ASSOC);
        if (!$shop || empty($shop['wc_url'])) {
            return; // Shop hat keine WooCommerce-Anbindung -- nichts zu spiegeln
        }

        $client = new WooCommerceClient($shop['wc_url'], $shop['wc_key'], $shop['wc_secret']);

        $payload = [
            'code'          => $gutschein['code'],
            'discount_type' => 'fixed_cart',
            'amount'        => number_format((float)$gutschein['restguthaben'], 2, '.', ''),
            'usage_limit'   => 1,
            'individual_use' => false,
            'description'   => 'MeaLana Gutschein ' . $gutschein['code'],
            'date_expires'  => $gutschein['gueltig_bis'],
        ];

        if (!empty($gutschein['woo_coupon_id'])) {
            $client->aktualisiereCoupon((int)$gutschein['woo_coupon_id'], $payload);
            return;
        }

        $ergebnis = $client->erstelleCoupon($payload);
        if (!empty($ergebnis['id'])) {
            $this->repo->updateWooCouponId($gutscheinId, (int)$ergebnis['id']);
        }
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
        $gutschein = $this->repo->findByCode(strtoupper(trim($code)));
        if (!$gutschein) {
            return ['erfolg' => false, 'fehler' => ['Gutschein-Code nicht gefunden.']];
        }
        if ($gutschein['status'] === 'storniert') {
            return ['erfolg' => false, 'fehler' => ['Dieser Gutschein wurde storniert.']];
        }
        if ($gutschein['status'] === 'eingeloest') {
            return ['erfolg' => false, 'fehler' => ['Dieser Gutschein wurde bereits vollständig eingelöst.']];
        }
        if ($gutschein['gueltig_bis'] !== null && $gutschein['gueltig_bis'] < date('Y-m-d')) {
            $this->repo->updateRestguthabenUndStatus((int)$gutschein['id'], (float)$gutschein['restguthaben'], 'abgelaufen');
            return ['erfolg' => false, 'fehler' => ['Dieser Gutschein ist abgelaufen.']];
        }

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
    public function versende(int $gutscheinId): array
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
