-- Migration 037: Merkmale-System Redesign
-- Neue Spalten auf merkmale, neue Tabellen merkmal_werte + merkmal_artikeltypen
-- Bestehende artikel_merkmale-Daten werden bereinigt (Testdaten)

-- 1. merkmale-Tabelle erweitern
ALTER TABLE merkmale
    ADD COLUMN slug VARCHAR(80) NOT NULL DEFAULT '' AFTER name,
    ADD COLUMN mehrfach_auswahl TINYINT(1) NOT NULL DEFAULT 0 AFTER filterbar,
    ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER mehrfach_auswahl;

-- 2. merkmal_werte (Level 2 — vordefinierte Werte pro Merkmal)
CREATE TABLE merkmal_werte (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    merkmal_id  INT UNSIGNED NOT NULL,
    wert        VARCHAR(255) NOT NULL,
    sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY fk_mw_merkmal (merkmal_id),
    CONSTRAINT fk_mw_merkmal FOREIGN KEY (merkmal_id) REFERENCES merkmale (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. merkmal_artikeltypen (Sichtbarkeit nach Artikeltyp — leer = gilt für alle)
CREATE TABLE merkmal_artikeltypen (
    merkmal_id    INT UNSIGNED NOT NULL,
    artikeltyp_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (merkmal_id, artikeltyp_id),
    CONSTRAINT fk_mat_merkmal    FOREIGN KEY (merkmal_id)    REFERENCES merkmale     (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_mat_artikeltyp FOREIGN KEY (artikeltyp_id) REFERENCES artikel_typen (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. artikel_merkmale umbauen: wert_text/wert_zahl/wert_bool → merkmal_wert_id
--    Bestehende Testdaten löschen (noch kein Produktivbetrieb)
TRUNCATE TABLE artikel_merkmale;

ALTER TABLE artikel_merkmale
    DROP COLUMN wert_text,
    DROP COLUMN wert_zahl,
    DROP COLUMN wert_bool,
    ADD COLUMN merkmal_wert_id INT UNSIGNED NOT NULL AFTER merkmal_id,
    ADD UNIQUE KEY uq_artikel_merkmal_wert (artikel_id, merkmal_wert_id),
    ADD CONSTRAINT fk_am_wert FOREIGN KEY (merkmal_wert_id) REFERENCES merkmal_werte (id) ON DELETE CASCADE ON UPDATE CASCADE;
