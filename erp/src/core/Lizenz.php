<?php
require_once __DIR__ . '/Database.php';

/**
 * Lizenz — was darf diese Installation (Ebene 1, Kundenseite)
 *
 * Entscheidungen Jacky 2026-11-01:
 *  - Kauf + Wartung: die Lizenz läuft nie ab, nur "Wartung bis" (Updates)
 *  - ohne gültige Lizenzdatei läuft NUR das Grundmodul (keine Testphase)
 *  - nicht lizenzierte Module: im Menü ausgegraut mit Schloss, vorhandene Daten bleiben lesbar
 *  - gebunden an Firmenname + UID (ersatzweise Steuernummer) aus den Einstellungen
 *
 * Lizenzdatei = JSON {"daten": "<JSON-Text>", "signatur": "<base64>"}. Signiert wird genau der
 * daten-Text (ECDSA P-256 / SHA-256 über OpenSSL), dadurch gibt es kein Problem mit
 * unterschiedlicher JSON-Formatierung. Öffentlicher Schlüssel: config/lizenz_public.pem.
 *
 * daten: format, lizenz_nr, ausgestellt_am, firma, uid, steuernummer,
 *        module {code: anzahl}, wartung_bis (Y-m-d, null = unbegrenzt), hauslizenz (bool)
 */
class Lizenz
{
    /** Module: Code => [Bezeichnung, Preis netto, Menge zählt?] — Preise lt. Jacky 2026-11-01 */
    public const MODULE = [
        'grundmodul'   => ['label' => 'Grundmodul (Verkauf, Lager, Picklisten, Packplatz)', 'preis' => 790, 'menge' => false],
        'kasse'        => ['label' => 'Kasse (inkl. Offline-/Messe-Kasse)', 'preis' => 590, 'menge' => true, 'preis_weitere' => 190, 'einheit' => 'Kassen'],
        'shop'         => ['label' => 'Shop-Anbindung',                    'preis' => 490, 'menge' => true, 'preis_weitere' => 190, 'einheit' => 'Shops'],
        'gutscheine'   => ['label' => 'Gutscheine',                        'preis' => 190, 'menge' => false],
        'plc'          => ['label' => 'Versand: PLC-Anbindung',            'preis' => 50,  'menge' => false],
        'lagerplaetze' => ['label' => 'Lagerplätze & Profi-Inventur',      'preis' => 390, 'menge' => false],
        'partner'      => ['label' => 'Partner / Mietfächer',              'preis' => 390, 'menge' => false],
        'haendler'     => ['label' => 'Händler-Konsignation',              'preis' => 290, 'menge' => false],
        'dropshipping' => ['label' => 'Dropshipping',                      'preis' => 190, 'menge' => false],
        'buchhaltung'  => ['label' => 'Buchhaltungs-Export',               'preis' => 190, 'menge' => false],
        'konfigurator' => ['label' => 'Konfigurator',                      'preis' => 190, 'menge' => false],
        'kundenanzeige'=> ['label' => 'Kundenanzeige',                     'preis' => 99,  'menge' => false],
        'paperless'    => ['label' => 'Paperless-Rechnung',                'preis' => 99,  'menge' => false],
    ];

    /**
     * Seiten je Modul: Verzeichnis (ab public/) => [Datei oder '*' => Modul].
     * Gleiche Tabelle steuert Seitensperre (Auth::pruefeSeite) und Schloss im Menü.
     */
    private const SEITEN = [
        'kasse'         => ['*' => 'kasse', 'ajax_kundenanzeige_sync.php' => 'kundenanzeige', 'ajax_konfigurator.php' => 'konfigurator'],
        'kundenanzeige' => ['*' => 'kundenanzeige'],
        'gutscheine'    => ['*' => 'gutscheine'],
        'partner'       => ['*' => 'partner'],
        'haendler'      => ['*' => 'haendler'],
        'buchhaltung'   => ['export.php' => 'buchhaltung', 'export_datei.php' => 'buchhaltung', 'export_einstellungen_speichern.php' => 'buchhaltung'],
        'lager'         => ['lagerplaetze.php' => 'lagerplaetze', 'lagerplaetze_aktualisieren.php' => 'lagerplaetze', 'lagerplaetze_etiketten.php' => 'lagerplaetze',
                            'lagerplaetze_serie.php' => 'lagerplaetze', 'lagerplaetze_speichern.php' => 'lagerplaetze', 'lagerplaetze_status_setzen.php' => 'lagerplaetze'],
        'artikel'       => ['konfigurator_ajax.php' => 'konfigurator'],
        'auftraege'     => ['dropshipping_aktion.php' => 'dropshipping'],
        'einstellungen' => ['shop_sync_start.php' => 'shop', 'shop_sync_pause.php' => 'shop', 'shop_sync_reconcile_start.php' => 'shop',
                            'shop_sync_bilder_ftp_start.php' => 'shop', 'shop_sync_bilder_export_start.php' => 'shop'],
    ];

    /**
     * Auch ohne Lizenz erreichbar (nur ansehen/drucken, nichts Neues anlegen) —
     * Aufbewahrungspflicht, bereits verkaufte Gutscheine müssen einlösbar bleiben.
     */
    private const LESEND = [
        'kasse'      => ['bon_journal.php', 'bon_druck.php', 'bon_a4.php', 'abschluss_liste.php', 'abschluss_druck.php',
                         'abschluss_periode.php', 'kassenbuch.php', 'bfr_log.php', 'messe_belege.php'],
        'gutscheine' => ['liste.php', 'detail.php', 'pdf_download.php', 'pruefen.php', 'einloesen.php', 'letzter_fuer_bon.php', 'versenden.php'],
        'partner'    => ['liste.php', 'detail.php', 'mietfaecher.php', 'beleg_pdf.php', 'verkaufsliste_pdf.php', 'abrechnung_pdf.php'],
        'haendler'   => ['liste.php', 'detail.php', 'beleg_pdf.php'],
        'lager'      => ['lagerplaetze.php'],
    ];

    private static ?array $cache = null;

    // ── Abfragen ──────────────────────────────────────────────────────────────

    public static function hat(string $modul): bool
    {
        return $modul === 'grundmodul' || self::anzahl($modul) > 0;
    }

    /** Lizenzierte Menge (Kassen, Shops); 0 = nicht lizenziert, 1 bei Modulen ohne Menge */
    public static function anzahl(string $modul): int
    {
        if ($modul === 'grundmodul') return 1;
        $s = self::status();
        return $s['status'] === 'aktiv' ? (int)($s['daten']['module'][$modul] ?? 0) : 0;
    }

    /**
     * status: aktiv | keine | ungueltig | falsche_firma
     * Rückgabe: status, daten (geprüfter Inhalt, auch bei falscher Firma zur Anzeige), fehler
     */
    public static function status(): array
    {
        if (self::$cache !== null) return self::$cache;

        $zeile = Database::getInstance()
            ->query("SELECT inhalt FROM lizenz_dateien WHERE aktiv = 1 ORDER BY id DESC LIMIT 1")
            ->fetchColumn();
        if (!$zeile) {
            return self::$cache = ['status' => 'keine', 'daten' => null, 'fehler' => null];
        }
        return self::$cache = self::pruefeInhalt($zeile);
    }

    /** Wartung bis (Y-m-d) oder null = unbegrenzt / keine Lizenz */
    public static function wartungBis(): ?string
    {
        $s = self::status();
        return $s['status'] === 'aktiv' ? ($s['daten']['wartung_bis'] ?? null) : null;
    }

    public static function wartungAbgelaufen(): bool
    {
        $s = self::status();
        if ($s['status'] !== 'aktiv') return false;
        $bis = $s['daten']['wartung_bis'] ?? null;
        return $bis !== null && $bis < date('Y-m-d');
    }

    // ── Seiten / Menü ─────────────────────────────────────────────────────────

    /** Modul einer Seite oder null (= Grundmodul, frei) */
    public static function modulFuerSeite(string $verzeichnis, string $datei): ?string
    {
        $regeln = self::SEITEN[$verzeichnis] ?? [];
        return $regeln[$datei] ?? $regeln['*'] ?? null;
    }

    /** Darf die Seite aufgerufen werden? (lizenziert ODER reine Lese-Seite) */
    public static function seiteErlaubt(string $verzeichnis, string $datei): bool
    {
        $modul = self::modulFuerSeite($verzeichnis, $datei);
        if ($modul === null || self::hat($modul)) return true;
        return in_array($datei, self::LESEND[$verzeichnis] ?? [], true);
    }

    /** Für das Menü: Modul hinter einem Link (BASE_PATH-URL) oder null */
    public static function modulFuerLink(string $href): ?string
    {
        $pfad = parse_url($href, PHP_URL_PATH) ?: '';
        if (defined('BASE_PATH') && BASE_PATH !== '' && str_starts_with($pfad, BASE_PATH)) {
            $pfad = substr($pfad, strlen(BASE_PATH));
        }
        $pfad = trim($pfad, '/');
        $verzeichnis = str_contains($pfad, '/') ? substr($pfad, 0, strrpos($pfad, '/')) : '';
        return self::modulFuerSeite($verzeichnis, basename($pfad));
    }

    /** Menüpunkt gesperrt? (Modul fehlt — auch wenn die Seite lesend erreichbar wäre) */
    public static function linkGesperrt(string $href): bool
    {
        $modul = self::modulFuerLink($href);
        return $modul !== null && !self::hat($modul);
    }

    public static function label(string $modul): string
    {
        return self::MODULE[$modul]['label'] ?? $modul;
    }

    // ── Prüfung ───────────────────────────────────────────────────────────────

    /**
     * Prüft nur die Signatur einer signierten Datei (Lizenz oder Superadmin-Übergabe).
     * Rückgabe: ['daten' => array|null, 'fehler' => ?string]
     */
    public static function pruefeSignatur(string $inhalt): array
    {
        $datei = json_decode($inhalt, true);
        if (!is_array($datei) || !isset($datei['daten'], $datei['signatur'])) {
            return ['daten' => null, 'fehler' => 'Keine gültige Datei von NAHTLOS.'];
        }
        $pubPfad = __DIR__ . '/../../config/lizenz_public.pem';
        $pub = is_file($pubPfad) ? openssl_pkey_get_public(file_get_contents($pubPfad)) : false;
        if (!$pub) {
            return ['daten' => null, 'fehler' => 'Öffentlicher Lizenzschlüssel fehlt (config/lizenz_public.pem).'];
        }
        $sig = base64_decode((string)$datei['signatur'], true);
        if ($sig === false || openssl_verify((string)$datei['daten'], $sig, $pub, OPENSSL_ALGO_SHA256) !== 1) {
            return ['daten' => null, 'fehler' => 'Signatur ungültig — die Datei wurde verändert oder stammt nicht von NAHTLOS.'];
        }
        $daten = json_decode((string)$datei['daten'], true);
        return is_array($daten) ? ['daten' => $daten, 'fehler' => null] : ['daten' => null, 'fehler' => 'Daten unlesbar.'];
    }

    /** Passen Firmenname + UID (bzw. Steuernummer) einer signierten Datei zu dieser Installation? */
    public static function firmaPasst(array $daten): bool
    {
        $firma = self::firmendaten();
        if (self::norm($daten['firma'] ?? '') !== self::norm($firma['firmenname'])) return false;
        return !empty($daten['uid'])
            ? self::norm($daten['uid']) === self::norm($firma['uid_nummer'])
            : (!empty($daten['steuernummer']) && self::norm($daten['steuernummer']) === self::norm($firma['steuernummer']));
    }

    /** Prüft eine Lizenzdatei (Text) gegen Signatur und Firmendaten, ohne sie zu speichern */
    public static function pruefeInhalt(string $inhalt): array
    {
        $sig = self::pruefeSignatur($inhalt);
        if (!$sig['daten']) {
            return ['status' => 'ungueltig', 'daten' => null, 'fehler' => $sig['fehler']];
        }
        $daten = $sig['daten'];
        if (($daten['typ'] ?? 'lizenz') !== 'lizenz' || empty($daten['lizenz_nr']) || !is_array($daten['module'] ?? null)) {
            return ['status' => 'ungueltig', 'daten' => null, 'fehler' => 'Das ist keine Lizenzdatei.'];
        }
        if (!self::firmaPasst($daten)) {
            return ['status' => 'falsche_firma', 'daten' => $daten,
                    'fehler' => 'Die Lizenz gilt für „' . ($daten['firma'] ?? '?') . '“ (' . ($daten['uid'] ?: ($daten['steuernummer'] ?? '')) . ') — '
                              . 'Firmenname/UID in den Einstellungen stimmen nicht überein.'];
        }
        return ['status' => 'aktiv', 'daten' => $daten, 'fehler' => null];
    }

    /** Neue Datei einspielen: prüfen, alte deaktivieren, neue aktiv setzen */
    public static function einspielen(string $inhalt, int $benutzerId): array
    {
        $erg = self::pruefeInhalt(trim($inhalt));
        if ($erg['status'] !== 'aktiv') {
            return ['erfolg' => false, 'fehler' => $erg['fehler']];
        }
        $db = Database::getInstance();
        $db->beginTransaction();
        $db->exec("UPDATE lizenz_dateien SET aktiv = 0 WHERE aktiv = 1");
        $db->prepare("INSERT INTO lizenz_dateien (lizenz_nr, inhalt, aktiv, eingespielt_von) VALUES (?, ?, 1, ?)")
           ->execute([$erg['daten']['lizenz_nr'], trim($inhalt), $benutzerId ?: null]);
        $db->commit();
        self::$cache = null;
        return ['erfolg' => true, 'daten' => $erg['daten']];
    }

    public static function firmendaten(): array
    {
        $rows = Database::getInstance()
            ->query("SELECT schluessel, wert FROM system_einstellungen WHERE schluessel IN ('firmenname','uid_nummer','steuernummer')")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        return [
            'firmenname'   => $rows['firmenname']   ?? '',
            'uid_nummer'   => $rows['uid_nummer']   ?? '',
            'steuernummer' => $rows['steuernummer'] ?? '',
        ];
    }

    /** Vergleich unabhängig von Groß-/Kleinschreibung, Leerzeichen und Satzzeichen */
    private static function norm(?string $s): string
    {
        return preg_replace('/[^a-z0-9äöüß]/u', '', mb_strtolower((string)$s));
    }

    /**
     * Lizenz-Generator (nur in Jackys eigener Installation): Ordner vorhanden UND Schlüsseldatei
     * eingetragen. Beim Kunden fehlt der Ordner src/modules/lizenzgenerator/ → immer false.
     */
    public static function generatorAktiv(): bool
    {
        $datei = __DIR__ . '/../modules/lizenzgenerator/LizenzGenerator.php';
        if (!is_file($datei)) return false;
        require_once $datei;
        return LizenzGenerator::aktiv();
    }

    /** Nur für Tests/nach dem Einspielen: Cache leeren */
    public static function neuLaden(): void
    {
        self::$cache = null;
    }
}
