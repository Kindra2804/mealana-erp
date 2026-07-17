-- Migration 126: Kontenplan (Basis fuer DATEV-Export)
-- Erloeskonten aus artikel_gruppen uebernehmen, Kernkonten (Kassa/Bank/Steuer) ergaenzen.

CREATE TABLE kontenplan (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    kontonummer VARCHAR(10)  NOT NULL,
    name        VARCHAR(100) NOT NULL,
    typ         ENUM('erloes','aufwand','steuer','bank','kasse') NOT NULL,
    aktiv       TINYINT(1)   NOT NULL DEFAULT 1,

    UNIQUE KEY uq_kontenplan_nr (kontonummer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO kontenplan (kontonummer, name, typ) VALUES
    ('1500', 'Kassa', 'kasse'),
    ('1600', 'Bank', 'bank'),
    ('2500', 'Umsatzsteuer', 'steuer'),
    ('2700', 'Vorsteuer', 'steuer');

INSERT INTO kontenplan (kontonummer, name, typ, aktiv)
SELECT konto_nr, name, 'erloes', aktiv FROM artikel_gruppen;
