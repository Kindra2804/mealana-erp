-- "Hersteller" ist EIN einziges globales WC-Attribut (anders als bei den
-- Achsen, wo mehrere unterschiedliche Attribute existieren und darum eine
-- eigene achsen_shops-Tabelle brauchen). Statt einer eigenen Ein-Zeilen-Tabelle
-- nur fuer diese eine Attribut-ID wird sie hier einfach mitgespeichert --
-- redundant pro Zeile, aber vermeidet eine Tabelle fuer einen einzigen Skalar.
ALTER TABLE hersteller_shops
    ADD COLUMN externe_attribut_id VARCHAR(100) NULL AFTER shop_id;
