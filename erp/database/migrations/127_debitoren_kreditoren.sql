-- Migration 127: Debitorenkonto (Kunden, automatisch aus Kundennummer) + Kreditorenkonto (Lieferanten, manuell)

ALTER TABLE kunden
    ADD COLUMN debitorennummer VARCHAR(10) NULL AFTER kundennummer;

UPDATE kunden
SET debitorennummer = CONCAT('2', LPAD(CAST(SUBSTRING(kundennummer, 4) AS UNSIGNED), 5, '0'))
WHERE kundennummer REGEXP '^KD-[0-9]+$';

ALTER TABLE kunden
    ADD UNIQUE KEY uq_kunden_debitorennummer (debitorennummer);

ALTER TABLE lieferanten
    ADD COLUMN kreditorennummer VARCHAR(10) NULL AFTER kundennummer;
