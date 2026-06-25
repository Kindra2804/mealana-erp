-- Migration 071: Packplatz-Infrastruktur
-- Versandtracking auf Aufträgen, Picklisten-Tabelle, PLC-Einstellung

ALTER TABLE auftraege
    ADD COLUMN IF NOT EXISTS versand_tracking  VARCHAR(100) NULL AFTER versandkosten,
    ADD COLUMN IF NOT EXISTS versand_datum     DATETIME     NULL AFTER versand_tracking;

-- Picklisten (Babsi erstellt, Packplatz scannt)
CREATE TABLE IF NOT EXISTS picklisten (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nummer       VARCHAR(30)  NOT NULL UNIQUE,   -- PL-2026-00001
    status       ENUM('offen','gedruckt','abgeschlossen') NOT NULL DEFAULT 'offen',
    erstellt_von INT          NULL,
    erstellt_am  DATETIME     NOT NULL DEFAULT NOW(),
    abgeschlossen_am DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zuordnung Pickliste ↔ Aufträge (1 Pickliste kann mehrere Aufträge haben)
CREATE TABLE IF NOT EXISTS pickliste_auftraege (
    pickliste_id INT UNSIGNED NOT NULL,
    auftrag_id   INT          NOT NULL,
    PRIMARY KEY (pickliste_id, auftrag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nummernkreis für Picklisten
INSERT IGNORE INTO dokument_nummern (typ, praefix, jahr, letzt_nr)
VALUES ('pickliste', 'PL', YEAR(NOW()), 0);

-- PLC-Ordner Einstellung (maschinenspezifisch, leer lassen wenn kein PLC)
INSERT IGNORE INTO system_einstellungen (schluessel, wert)
VALUES ('plc_polling_ordner', '');
