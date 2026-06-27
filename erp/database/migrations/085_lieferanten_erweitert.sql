-- Migration 085: Lieferanten – erweiterte Stammdaten
-- Zahlungskonditionen, Lieferkonditionen, Adresse, interne Notizen

ALTER TABLE lieferanten
    ADD COLUMN strasse            VARCHAR(255)      NULL            AFTER land,
    ADD COLUMN plz                VARCHAR(20)       NULL            AFTER strasse,
    ADD COLUMN ort                VARCHAR(100)      NULL            AFTER plz,
    ADD COLUMN kundennummer       VARCHAR(100)      NULL COMMENT 'Unsere Kundennummer beim Lieferanten' AFTER ort,
    ADD COLUMN waehrung           CHAR(3)           NOT NULL DEFAULT 'EUR' AFTER kundennummer,
    ADD COLUMN zahlungsziel_tage  SMALLINT UNSIGNED NULL COMMENT 'Zahlungsziel in Tagen, z.B. 30' AFTER waehrung,
    ADD COLUMN skonto_prozent     DECIMAL(5,2)      NULL COMMENT 'Skonto-Prozentsatz, z.B. 2.00' AFTER zahlungsziel_tage,
    ADD COLUMN skonto_tage        SMALLINT UNSIGNED NULL COMMENT 'Tage für Skonto-Abzug, z.B. 14' AFTER skonto_prozent,
    ADD COLUMN mindestbestellwert DECIMAL(10,2)     NULL            AFTER skonto_tage,
    ADD COLUMN lieferzeit_tage    SMALLINT UNSIGNED NULL COMMENT 'Standard-Lieferzeit in Tagen' AFTER mindestbestellwert,
    ADD COLUMN lieferbedingung    VARCHAR(50)       NULL COMMENT 'frei_haus | ab_werk | ab_lager | sonstige' AFTER lieferzeit_tage,
    ADD COLUMN interne_notizen    TEXT              NULL            AFTER lieferbedingung;
