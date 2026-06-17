-- Migration 038: brutto_ek in artikel_lieferanten + system_einstellungen Tabelle

-- 1. Brutto-EK beim Lieferanten
ALTER TABLE artikel_lieferanten
    ADD COLUMN brutto_ek DECIMAL(8,2) DEFAULT NULL AFTER netto_ek;

-- 2. Systemweite Einstellungen (Basis für Kleinunternehmer-Modus u.a.)
CREATE TABLE system_einstellungen (
    schluessel  VARCHAR(80)   NOT NULL,
    wert        VARCHAR(255)  NOT NULL DEFAULT '',
    PRIMARY KEY (schluessel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Standard: normale Besteuerung
INSERT INTO system_einstellungen (schluessel, wert) VALUES ('besteuerungsart', 'normal');
