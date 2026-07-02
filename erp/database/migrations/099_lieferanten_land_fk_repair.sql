-- Migration 099: Nachzügler zu 098 – Collation-Fix laender, Land-Altdaten, FK, Vertreter-Anrede

ALTER TABLE laender CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

UPDATE lieferanten SET land = 'SE' WHERE land = 'SW';
UPDATE lieferanten SET land = 'AT' WHERE land NOT IN (SELECT iso_code FROM laender);

ALTER TABLE lieferanten
    MODIFY COLUMN land CHAR(2) NOT NULL DEFAULT 'AT',
    ADD CONSTRAINT fk_lieferant_land FOREIGN KEY (land) REFERENCES laender (iso_code) ON UPDATE CASCADE;

ALTER TABLE lieferanten_vertreter
    ADD COLUMN anrede ENUM('herr','frau','divers') NULL AFTER lieferant_id;
