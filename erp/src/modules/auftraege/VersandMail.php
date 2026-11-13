<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Mailer.php';
require_once __DIR__ . '/../dokumente/BelegBilder.php';

/**
 * Versandbestätigung an den Kunden (Tracking-Link + versendete Positionen).
 * Gemeinsam genutzt vom Packplatz (eigener Versand) und vom Dropshipping
 * (Lieferant hat direkt an den Kunden geschickt).
 */
class VersandMail
{
    public const TRACKING_URLS = [
        'post_at' => 'https://www.post.at/sv/sendungsdetails?snr=',
        'dhl'     => 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode=',
        'dpd'     => 'https://tracking.dpd.de/status/de_DE/parcel/',
        'gls'     => 'https://gls-group.eu/track/',
        'manuell' => '',
    ];

    public const DIENSTLEISTER = [
        'post_at' => 'Österreichische Post',
        'dhl'     => 'DHL',
        'dpd'     => 'DPD',
        'gls'     => 'GLS',
        'manuell' => 'Paketdienst',
    ];

    /**
     * $positionen: je Eintrag artikel_id, bezeichnung, artikelnummer, menge_versendet, einheit.
     * Gibt false zurück, wenn der Kunde keine E-Mail-Adresse hat oder der Versand scheitert.
     */
    public static function sende(
        array $auftrag,
        string $tracking,
        string $versanddienstleister,
        bool $istTeillieferung,
        array $positionen,
        array $anhaenge = []
    ): bool {
        $kunden = json_decode($auftrag['kunden_snapshot'] ?? '{}', true) ?: [];
        $email  = $kunden['email'] ?? '';
        if (!$email) return false;

        if (!empty($auftrag['lieferadresse_snapshot'])) {
            $lieferAdr = json_decode($auftrag['lieferadresse_snapshot'], true);
        } elseif (!empty($auftrag['rechnungsadresse_snapshot'])) {
            $lieferAdr = json_decode($auftrag['rechnungsadresse_snapshot'], true);
        } else {
            $lieferAdr = $kunden;
        }
        $nachname = $kunden['nachname'] ?? '';
        $name     = trim(($kunden['vorname'] ?? '') . ' ' . $nachname) ?: ($kunden['firma'] ?? '');

        $firma = Database::getInstance()
            ->query("SELECT schluessel, wert FROM system_einstellungen")
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        $baseUrl     = self::TRACKING_URLS[$versanddienstleister] ?? '';
        $trackingUrl = $baseUrl ? $baseUrl . urlencode($tracking) : '';
        $betreff     = $istTeillieferung
            ? 'Teillieferung zu Auftrag ' . $auftrag['auftrag_nr'] . ' versendet'
            : 'Ihr Auftrag ' . $auftrag['auftrag_nr'] . ' wurde versendet';

        try {
            $mailer = (new Mailer())->fuerShop((int)($auftrag['shop_id'] ?? 0))->fuerPartnerAuftrag($auftrag);
            $mailer->sendeTemplate(
                empfaenger:   $email,
                betreff:      $betreff,
                templatePfad: 'mails/versandbestaetigung.html.twig',
                variablen: [
                    'logo_base64'               => $mailer->ladeShopLogo((int)($auftrag['shop_id'] ?? 1)),
                    'anrede'                    => $kunden['anrede'] ?? '',
                    'nachname'                  => $nachname,
                    'kunde_name'                => $name,
                    'auftrag_nummer'            => $auftrag['auftrag_nr'],
                    'tracking'                  => $tracking,
                    'tracking_url'              => $trackingUrl,
                    'versanddienstleister_label'=> self::DIENSTLEISTER[$versanddienstleister] ?? $versanddienstleister,
                    'ist_teillieferung'         => $istTeillieferung,
                    'positionen'                => BelegBilder::anreichern($positionen, 'mail_versand'),
                    'mit_bild'                  => BelegBilder::aktiv('mail_versand'),
                    'lieferadresse'             => $lieferAdr,
                    'firma_email'               => $firma['mail_from_address'] ?? '',
                ],
                anhaenge: $anhaenge,
            );
            return true;
        } catch (Throwable $e) {
            error_log('[Versandmail] Fehler: ' . $e->getMessage());
            return false;
        }
    }
}
