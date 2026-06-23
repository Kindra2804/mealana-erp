-- Migration 059: lieferzeit_text auf bestellung_positionen
-- War im Repository-Code referenziert aber fehlte in der ursprünglichen Migration 057

ALTER TABLE bestellung_positionen
    ADD COLUMN lieferzeit_text VARCHAR(100) NULL AFTER ek_preis;
