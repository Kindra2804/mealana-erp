-- Migration 055: Meldebestand, Sicherheitsbestand, Standardbestellmenge auf artikel
-- Basis für Bestellvorschlag-Infobox und spätere automatische Vorschläge

ALTER TABLE artikel
    ADD COLUMN meldebestand         INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Auslöser Bestellvorschlag — bei Unterschreitung Infobox im Bestellwesen',
    ADD COLUMN sicherheitsbestand   INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Puffer der nie unterschritten werden soll',
    ADD COLUMN standardbestellmenge INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Vorschlagsmenge beim manuellen Bestellvorschlag';
