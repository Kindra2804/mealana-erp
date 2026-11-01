<?php
/**
 * LizenzSigner — Lizenzdateien ausstellen (Ebene 2, nur bei Jacky)
 *
 * Gehört NICHT zum ERP beim Kunden. Der private Schlüssel liegt als Datei außerhalb
 * des Repos (Standard: D:\ERP\lizenz_schluessel\nahtlos_lizenz_privat.pem) und kommt nie in
 * eine Datenbank. Format der Datei siehe erp/src/core/Lizenz.php.
 */
class LizenzSigner
{
    public const PRIVAT_STANDARD = 'D:/ERP/lizenz_schluessel/nahtlos_lizenz_privat.pem';

    /** Neues Schlüsselpaar (ECDSA P-256). Gibt [privat_pem, public_pem] zurück. */
    public static function schluesselErzeugen(): array
    {
        $opt = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
        // Windows/XAMPP: OpenSSL findet seine Konfiguration sonst nicht (nur fürs Erzeugen nötig)
        foreach (['C:/xampp/php/extras/ssl/openssl.cnf', 'C:/xampp/apache/conf/openssl.cnf'] as $cnf) {
            if (is_file($cnf)) { $opt['config'] = $cnf; break; }
        }
        $key = openssl_pkey_new($opt);
        if (!$key) throw new RuntimeException('OpenSSL konnte keinen Schlüssel erzeugen: ' . openssl_error_string());
        openssl_pkey_export($key, $privat, null, $opt);
        $public = openssl_pkey_get_details($key)['key'];
        return [$privat, $public];
    }

    /**
     * Superadmin-Übergabe-Schlüssel: einmalig (nonce), gebunden an Firma + UID/Steuernummer,
     * gültig $tage Tage. Vorher Identität prüfen (schriftlich vom Inhaber/Geschäftsführer)!
     */
    public static function signiereUebergabe(string $nr, string $firma, string $uid, string $steuernr, int $tage = 7,
                                             string $privatPfad = self::PRIVAT_STANDARD): string
    {
        if ($uid === '' && $steuernr === '') throw new InvalidArgumentException('UID oder Steuernummer ist Pflicht.');
        $daten = [
            'format' => 1, 'typ' => 'superadmin_uebergabe', 'schluessel_nr' => $nr,
            'firma' => $firma, 'uid' => $uid, 'steuernummer' => $steuernr,
            'nonce' => bin2hex(random_bytes(16)),
            'ausgestellt_am' => date('Y-m-d'), 'gueltig_bis' => date('Y-m-d', strtotime(sprintf('%+d days', $tage))),
        ];
        return self::signiereText(json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $privatPfad);
    }

    private static function signiereText(string $text, string $privatPfad): string
    {
        if (!is_file($privatPfad)) throw new RuntimeException("Privater Schlüssel nicht gefunden: $privatPfad");
        $key = openssl_pkey_get_private(file_get_contents($privatPfad));
        if (!$key) throw new RuntimeException('Privater Schlüssel ungültig.');
        if (!openssl_sign($text, $sig, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Signieren fehlgeschlagen: ' . openssl_error_string());
        }
        return json_encode(['daten' => $text, 'signatur' => base64_encode($sig)],
                           JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Signiert die Lizenzdaten. Pflicht: lizenz_nr, firma, uid oder steuernummer, module.
     * Rückgabe: Inhalt der Lizenzdatei (JSON-Text).
     */
    public static function signiere(array $daten, string $privatPfad = self::PRIVAT_STANDARD): string
    {
        foreach (['lizenz_nr', 'firma', 'module'] as $pflicht) {
            if (empty($daten[$pflicht])) throw new InvalidArgumentException("Pflichtfeld fehlt: $pflicht");
        }
        if (empty($daten['uid']) && empty($daten['steuernummer'])) {
            throw new InvalidArgumentException('UID oder Steuernummer ist Pflicht.');
        }
        $daten = ['format' => 1, 'typ' => 'lizenz', 'ausgestellt_am' => date('Y-m-d')] + $daten + [
            'uid' => '', 'steuernummer' => '', 'wartung_bis' => null, 'hauslizenz' => false,
        ];
        $daten['module']['grundmodul'] = 1;

        return self::signiereText(json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $privatPfad);
    }
}
