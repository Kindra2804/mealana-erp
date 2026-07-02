-- Migration 098: Lieferanten – Firma/Firmenzusatz, UStID, Steuerregel, Bankverbindung, Land-FK; Vertreter-Anrede

ALTER TABLE lieferanten
    ADD COLUMN firma                 VARCHAR(255)  NULL AFTER name,
    ADD COLUMN firmenzusatz          VARCHAR(255)  NULL AFTER firma,
    ADD COLUMN ustid                 VARCHAR(30)   NULL COMMENT 'USt-IdNr., z.B. ATU12345678' AFTER kundennummer,
    ADD COLUMN steuerregel           ENUM('inland','eu_igl','drittland_einfuhr','reverse_charge')
                                      NOT NULL DEFAULT 'inland' AFTER ustid,
    ADD COLUMN standard_lieferkosten DECIMAL(10,2) NULL COMMENT 'Vorbelegung für Bestellung, dort überschreibbar' AFTER mindestbestellwert,
    ADD COLUMN iban                  VARCHAR(34)   NULL AFTER interne_notizen,
    ADD COLUMN bic                   VARCHAR(11)   NULL AFTER iban,
    ADD COLUMN bank_name             VARCHAR(255)  NULL AFTER bic,
    ADD COLUMN kontoinhaber          VARCHAR(255)  NULL COMMENT 'nur befüllen wenn abweichend von Firma' AFTER bank_name;

-- Freitext-Altdaten auf gültigen ISO-Code bereinigen
UPDATE lieferanten SET land = 'AT' WHERE land NOT IN (SELECT iso_code FROM laender);

ALTER TABLE lieferanten
    MODIFY COLUMN land CHAR(2) NOT NULL DEFAULT 'AT',
    ADD CONSTRAINT fk_lieferant_land FOREIGN KEY (land) REFERENCES laender (iso_code) ON UPDATE CASCADE;

ALTER TABLE lieferanten_vertreter
    ADD COLUMN anrede ENUM('herr','frau','divers') NULL AFTER lieferant_id;
