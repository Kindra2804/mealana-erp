-- Natives WooCommerce-"Hersteller" (Produktsicherheit-Panel, /wc/v3/products/manufacturers)
-- ist eine ZWEITE, unabhaengige Hersteller-Entitaet neben dem bereits gebauten WC-Attribut-Weg
-- (Jackys Entscheidung 2026-07-23: beide parallel befuellen, "schadet nicht"). Anders als das
-- Attribut hat dieser Weg eigene Adress-/EU-Vertreter-Felder (formatted_address/formatted_eu_address)
-- UND generiert automatisch eine Hersteller-Archivseite (/hersteller/{slug}/, mit allen
-- zugewiesenen Produkten) -- verifiziert gegen indra-design.at.
ALTER TABLE hersteller_shops
    ADD COLUMN externe_manufacturer_id VARCHAR(100) NULL AFTER externe_term_id;
