<?php

class Encryption
{
    private const CIPHER  = 'aes-256-gcm';
    private const IV_LEN  = 12;  // GCM-Standard: 96 Bit
    private const TAG_LEN = 16;  // Auth-Tag: 128 Bit

    private static ?string $masterKey = null;
    private static ?string $searchKey = null;

    private static function loadKeys(): void
    {
        if (self::$masterKey !== null) return;
        $config = require __DIR__ . '/../../config/encryption.php';
        self::$masterKey = hex2bin($config['master_key']);
        self::$searchKey = hex2bin($config['search_key']);
    }

    // Verschlüsselt einen String. Gibt NULL zurück wenn Eingabe NULL ist.
    // Rückgabe-Format: [12 Byte IV] + [16 Byte Auth-Tag] + [Ciphertext] → als BLOB gespeichert
    public static function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null) return null;
        self::loadKeys();
        $iv  = random_bytes(self::IV_LEN);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext, self::CIPHER, self::$masterKey,
            OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN
        );
        return $iv . $tag . $ciphertext;
    }

    // Entschlüsselt einen BLOB-Wert. Gibt NULL zurück bei NULL-Eingabe oder Fehler.
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
        return $result === false ? null : $result;
    }

    // HMAC-SHA256 Hash für Suche (z.B. E-Mail-Lookup via WHERE email_hash = ?)
    // Normalisiert: strtolower + trim, damit Groß/Kleinschreibung kein Problem ist
    public static function hash(?string $value): ?string
    {
        if ($value === null) return null;
        self::loadKeys();
        return hash_hmac('sha256', strtolower(trim($value)), self::$searchKey);
    }
}
