-- Migration 068: Lieferzeit am Artikel + Kontaktnotiz am Auftrag
-- artikel.lieferzeit_text: wird auf AB angedruckt wenn Artikel nicht lagernd
-- auftraege.kontakt_notiz: "WhatsApp wenn da" etc. für Laufkunden-Abholaufträge

ALTER TABLE artikel
    ADD COLUMN IF NOT EXISTS lieferzeit_text VARCHAR(100) NULL;

ALTER TABLE auftraege
    ADD COLUMN IF NOT EXISTS kontakt_notiz VARCHAR(255) NULL AFTER notiz_versand;
