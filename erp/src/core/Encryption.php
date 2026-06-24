<?php

/**
 * Encryption – AES-256-GCM Verschlüsselung für DSGVO-Pflichtfelder
 *
 * Wird ausschließlich für Kundendaten verwendet (Name, E-Mail, Adresse, etc.).
 * Schlüssel kommen aus config/encryption.php:
 *   master_key  → 32-Byte-Hex, für encrypt/decrypt
 *   search_key  → 32-Byte-Hex, für HMAC-Hashes (Suche via email_hash)
 *
 * Blob-Format in der DB: [IV (12 Byte)] [Auth-Tag (16 Byte)] [Ciphertext]
 * → Alles binär in einer BLOB-Spalte gespeichert.
 *
 * Warum GCM? Authenticated Encryption — der Auth-Tag erkennt Manipulation.
 * Wenn der Tag bei decrypt() nicht stimmt, gibt openssl_decrypt() false zurück.
 *
 * Crypto-Shredding (DSGVO "Recht auf Vergessen"):
 * Den Schlüssel vernichten → alle verschlüsselten Daten unlesbar,
 * ohne jeden Datensatz einzeln löschen zu müssen.
 */
class Encryption
{
    private const CIPHER  = 'aes-256-gcm';
    private const IV_LEN  = 12;  // GCM-Standard: 96 Bit IV
    private const TAG_LEN = 16;  // GCM-Auth-Tag: 128 Bit

    /** Gecachter Master-Key (binary), null bis zum ersten loadKeys()-Aufruf. */
    private static ?string $masterKey = null;
    /** Gecachter Search-Key (binary) für HMAC-Hashes. */
    private static ?string $searchKey = null;

    /**
     * Liest beide Schlüssel aus der Config-Datei (einmalig, dann gecacht).
     * config/encryption.php liefert ['master_key' => '...hex...', 'search_key' => '...hex...']
     */
    private static function loadKeys(): void
    {
        if (self::$masterKey !== null) return;
        $config = require __DIR__ . '/../../config/encryption.php';
        self::$masterKey = hex2bin($config['master_key']);
        self::$searchKey = hex2bin($config['search_key']);
    }

    /**
     * Verschlüsselt einen String mit AES-256-GCM.
     * Gibt NULL zurück wenn die Eingabe NULL ist (leere Felder bleiben NULL in der DB).
     *
     * Jede Verschlüsselung bekommt einen frischen zufälligen IV →
     * gleicher Klartext ergibt immer unterschiedliche Ciphertexte (keine Pattern-Leaks).
     *
     * @return string|null BLOB aus [IV][Auth-Tag][Ciphertext]
     */
    public static function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null) return null;
        self::loadKeys();
        $iv  = random_bytes(self::IV_LEN);  // Frischer IV pro Verschlüsselung
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext, self::CIPHER, self::$masterKey,
            OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN
        );
        return $iv . $tag . $ciphertext;
    }

    /**
     * Entschlüsselt einen BLOB-Wert aus der Datenbank.
     * Gibt NULL zurück bei NULL-Eingabe oder wenn der Auth-Tag nicht stimmt
     * (Manipulation oder falscher Schlüssel).
     */
    public static function decrypt(?string $stored): ?string
    {
        if ($stored === null) return null;
        self::loadKeys();
        $iv         = substr($stored, 0, self::IV_LEN);
        $tag        = substr($stored, self::IV_LEN, self::TAG_LEN);
        $ciphertext = substr($stored, self::IV_LEN + self::TAG_LEN);
        $result = openssl_decrypt(
            $ciphertext, self::CIPHER, self::$masterKey,
            OPENSSL_RAW_DATA, $iv, $tag
        );
        // openssl_decrypt gibt false zurück wenn Auth-Tag nicht stimmt
        return $result === false ? null : $result;
    }

    /**
     * Erstellt einen deterministischen HMAC-SHA256 Hash für die Suche.
     *
     * Da verschlüsselte Felder kein SQL-LIKE erlauben, wird die E-Mail
     * zusätzlich als Hash gespeichert (email_hash). So sind exakte
     * Duplikat-Checks (findByEmailHash) möglich, ohne zu entschlüsseln.
     *
     * Normalisiert: strtolower + trim → E-Mails sind case-insensitiv eindeutig.
     * Eigener search_key (getrennt vom master_key) verhindert Cross-Use der Schlüssel.
     */
    public static function hash(?string $value): ?string
    {
        if ($value === null) return null;
        self::loadKeys();
        return hash_hmac('sha256', strtolower(trim($value)), self::$searchKey);
    }
}
