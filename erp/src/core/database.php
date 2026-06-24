<?php

/**
 * Database – PDO-Singleton
 *
 * Stellt genau eine PDO-Verbindung zur MariaDB bereit.
 * Alle Repository-Klassen holen sich die Verbindung über
 * Database::getInstance() — niemals direkt `new PDO(...)`.
 *
 * Verbindungsparameter stehen in config/database.php (host, dbname,
 * charset, username, password) — dort getrennt von Code.
 *
 * Singleton-Muster: Verhindert mehrere gleichzeitige Verbindungen
 * und spart Verbindungsaufbau-Overhead pro Request.
 */
class Database
{
    /** Die einzige aktive PDO-Verbindung; null bis zum ersten Aufruf. */
    private static ?PDO $instance = null;

    /**
     * Gibt die gemeinsame PDO-Instanz zurück.
     * Legt sie beim allerersten Aufruf an (lazy initialization).
     * Danach wird dieselbe Instanz wiederverwendet.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/database.php';

            $dsn = "mysql:host={$config['host']};
                    dbname={$config['dbname']};
                    charset={$config['charset']}";

            self::$instance = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    // Exceptions statt stiller Fehler — SQL-Fehler sind sofort sichtbar
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    // fetchAll() liefert standardmäßig assoziative Arrays
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }

        return self::$instance;
    }

    /** Privater Konstruktor verhindert direkte Instanziierung (Singleton). */
    private function __construct() {}
}
