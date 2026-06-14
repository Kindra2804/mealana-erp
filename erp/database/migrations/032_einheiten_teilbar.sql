ALTER TABLE artikel_typen
    ADD COLUMN teilbar TINYINT(1) NOT NULL DEFAULT 0 AFTER name;

UPDATE artikel_typen SET teilbar = 1 WHERE code = 'METERWARE';
