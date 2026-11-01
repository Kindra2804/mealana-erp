<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Lizenz.php';

/**
 * Onboarding / Einrichtungs-Assistent.
 *
 * Pflicht-Schritte (Firma, Superadmin, Lizenz, Steuer, Lager): ohne sie läuft das ERP nicht — jede Seite
 * leitet auf /einrichtung/ um. Empfohlene Schritte hängen an der Lizenz (Kasse, Shop + Abholorte): solange
 * einer offen ist, kommt der Assistent nach jeder Anmeldung des Superadmins wieder. Ein Lizenz-Upgrade macht
 * neue Schritte automatisch offen. „Erledigt“ wird an den echten Daten erkannt, bei Superadmin, Lizenz
 * (nur Grundmodul), Steuer, Benutzer (allein) und Abholorte (keine) an einer bewussten Bestätigung.
 */
class OnboardingService
{
    /** Bestätigungen in system_einstellungen */
    public const FLAG = [
        'superadmin' => 'onboarding_superadmin',
        'lizenz'     => 'onboarding_nur_grundmodul',
        'steuer'     => 'onboarding_steuer',
        'benutzer'   => 'onboarding_allein',
        'abholorte'  => 'onboarding_keine_abholung',
    ];

    private static ?array $cache = null;

    /**
     * Alle für diese Lizenz relevanten Schritte.
     * @return array<string, array{titel: string, pflicht: bool, erledigt: bool, info: string, link: ?string}>
     */
    public static function schritte(): array
    {
        if (self::$cache !== null) return self::$cache;
        $db = Database::getInstance();
        $e  = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);
        $w  = fn(string $k) => trim((string)($e[$k] ?? ''));
        $flag = fn(string $schritt) => $w(self::FLAG[$schritt]) === '1';
        $zahl = fn(string $sql) => (int)$db->query($sql)->fetchColumn();
        $lizenz = Lizenz::status();

        $s = [];
        $s['firma'] = ['titel' => 'Firma', 'pflicht' => true,
            'erledigt' => $w('firmenname') !== '' && $w('strasse') !== '' && $w('plz') !== '' && $w('ort') !== ''
                          && ($w('uid_nummer') !== '' || $w('steuernummer') !== ''),
            'info' => trim($w('firmenname') . ($w('uid_nummer') ? ' · ' . $w('uid_nummer') : ($w('steuernummer') ? ' · St.Nr. ' . $w('steuernummer') : ''))),
            'link' => null];
        $s['superadmin'] = ['titel' => 'Superadmin', 'pflicht' => true, 'erledigt' => $flag('superadmin'),
            'info' => $flag('superadmin') ? 'eigenes Konto: ' . ($_SESSION['benutzer']['formularname'] ?? '') : 'eigener Name, E-Mail und Passwort', 'link' => null];
        $s['lizenz'] = ['titel' => 'Lizenz', 'pflicht' => true,
            'erledigt' => $lizenz['status'] === 'aktiv' || ($lizenz['status'] === 'keine' && $flag('lizenz')),
            'info' => match ($lizenz['status']) {
                'aktiv'         => 'Lizenz ' . ($lizenz['daten']['lizenz_nr'] ?? ''),
                'falsche_firma' => 'Lizenz passt nicht zu den Firmendaten',
                'ungueltig'     => 'Lizenzdatei ungültig',
                default         => $flag('lizenz') ? 'nur Grundmodul' : '.lic einspielen oder nur Grundmodul',
            },
            'link' => null];
        $s['steuer'] = ['titel' => 'Steuer', 'pflicht' => true, 'erledigt' => $flag('steuer'),
            'info' => 'regelbesteuert · Bilanz · Soll-Versteuerung', 'link' => null];
        $lager = $db->query("SELECT name FROM lager WHERE aktiv = 1 AND lager_beziehung = 'eigen' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $s['lager'] = ['titel' => 'Lager', 'pflicht' => true, 'erledigt' => count($lager) > 0,
            'info' => $lager ? implode(', ', array_slice($lager, 0, 3)) . (count($lager) > 3 ? ' …' : '') : 'wird automatisch angelegt',
            'link' => null];

        $mitarbeiter = $zahl("SELECT COUNT(DISTINCT b.id) FROM benutzer b JOIN benutzer_rollen br ON br.benutzer_id = b.id
                              JOIN rollen r ON r.id = br.rolle_id WHERE b.aktiv = 1 AND r.rang < 100");
        $s['benutzer'] = ['titel' => 'Benutzer', 'pflicht' => false, 'erledigt' => $mitarbeiter > 0 || $flag('benutzer'),
            'info' => $mitarbeiter > 0 ? $mitarbeiter . ' Mitarbeiter-Konto' . ($mitarbeiter > 1 ? 'en' : '')
                    : ($flag('benutzer') ? 'arbeite allein' : 'Mitarbeiter mit eigenen Rechten, nicht alle als Superadmin'),
            'link' => '/benutzer/liste.php'];
        $s['mail'] = ['titel' => 'Mail', 'pflicht' => false,
            'erledigt' => $w('mail_smtp_host') !== '' && $w('mail_aktiv') === '1',
            'info' => $w('mail_smtp_host') !== '' ? $w('mail_smtp_host') . ($w('mail_aktiv') === '1' ? '' : ' (Versand noch aus)') : 'SMTP + Test-Mail',
            'link' => '/einstellungen/index.php?tab=mail'];
        if (Lizenz::hat('kasse')) {
            $kassen = $zahl("SELECT COUNT(*) FROM kassen WHERE aktiv = 1");
            $s['kasse'] = ['titel' => 'Kasse', 'pflicht' => false, 'erledigt' => $kassen > 0,
                'info' => $kassen > 0 ? $kassen . ' Kasse' . ($kassen > 1 ? 'n' : '') . ' (RKSV unter Einstellungen → Kassen)' : 'Kasse anlegen, RKSV/BFR später',
                'link' => '/einstellungen/index.php?tab=kassen'];
        }
        if (Lizenz::hat('shop')) {
            $shops = $zahl("SELECT COUNT(*) FROM shops WHERE wc_url IS NOT NULL AND wc_url <> ''");
            $s['shop'] = ['titel' => 'Shop', 'pflicht' => false, 'erledigt' => $shops > 0,
                'info' => $shops > 0 ? $shops . ($shops > 1 ? ' Kanäle' : ' Kanal') : 'Kanal mit Adresse anlegen, Schlüssel später',
                'link' => '/einstellungen/index.php?tab=kanaele'];
            $abholorte = $zahl("SELECT COUNT(*) FROM abholorte");
            $s['abholorte'] = ['titel' => 'Abholorte', 'pflicht' => false, 'erledigt' => $abholorte > 0 || $flag('abholorte'),
                'info' => $abholorte > 0 ? $abholorte . ' Abholort' . ($abholorte > 1 ? 'e' : '') : ($flag('abholorte') ? 'keine Selbstabholung' : 'Adresse für Selbstabholung'),
                'link' => '/einstellungen/index.php?tab=abholorte'];
        }
        return self::$cache = $s;
    }

    public static function pflichtOffen(): bool
    {
        foreach (self::schritte() as $sch) if ($sch['pflicht'] && !$sch['erledigt']) return true;
        return false;
    }

    public static function anzahlOffen(): int
    {
        return count(array_filter(self::schritte(), fn($sch) => !$sch['erledigt']));
    }

    /** Lager ist Pflicht, aber ohne Rückfrage: fehlt ein eigenes, wird „Standardlager“ angelegt */
    public static function standardlagerSicherstellen(): void
    {
        $db = Database::getInstance();
        if ((int)$db->query("SELECT COUNT(*) FROM lager WHERE aktiv = 1 AND lager_beziehung = 'eigen'")->fetchColumn() > 0) return;
        $db->exec("INSERT INTO lager (name, typ, aktiv, lager_beziehung) VALUES ('Standardlager', 'ladengeschaeft', 1, 'eigen')");
        self::neuLaden();
    }

    public static function bestaetigen(string $schritt, bool $wert = true): void
    {
        if (!isset(self::FLAG[$schritt])) throw new InvalidArgumentException('Unbekannter Schritt ' . $schritt);
        Database::getInstance()
            ->prepare("INSERT INTO system_einstellungen (schluessel, wert) VALUES (?, ?) ON DUPLICATE KEY UPDATE wert = VALUES(wert)")
            ->execute([self::FLAG[$schritt], $wert ? '1' : '0']);
        self::neuLaden();
    }

    public static function neuLaden(): void
    {
        self::$cache = null;
        unset($_SESSION['onboarding_pflicht_ok']);
    }

    /**
     * Sperre für Auth::pruefeSeite: true = diese Seite darf trotz offener Pflicht-Schritte aufgerufen werden.
     * Erlaubt sind der Assistent selbst und die Speicher-Ziele, die er benutzt.
     */
    public static function seiteFrei(string $verzeichnis, string $datei): bool
    {
        if ($verzeichnis === 'einrichtung') return true;
        return in_array($verzeichnis . '/' . $datei, [
            'lizenz/hochladen.php', 'einstellungen/speichern.php', 'logout.php', '/logout.php',
        ], true) || ($verzeichnis === '' && in_array($datei, ['logout.php', 'heartbeat.php'], true));
    }

    /** Session-Cache: sind die Pflicht-Schritte einmal erledigt, nicht bei jedem Klick neu prüfen */
    public static function pflichtErledigtGecacht(): bool
    {
        if (!empty($_SESSION['onboarding_pflicht_ok'])) return true;
        if (self::pflichtOffen()) return false;
        $_SESSION['onboarding_pflicht_ok'] = true;
        return true;
    }
}
