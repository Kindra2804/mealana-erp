-- Migration 214: Shop-kürzel für Belege mit anführen
--
-- Belege werden nach Shops getrennt abgelegt (eigene Nummernkreise)
-- Hauptshop und manuelle Eingaben erhalten '' (Standard)
-- Subshops bekommen einstellbares Kürzel (änderbar bis zum ersten Beleg) 
-- Kürzel steht zwischen Präfix und Jahr (z.B. R-BIO-2026-00001)

ALTER TABLE shops
    ADD COLUMN nr_kuerzel VARCHAR(5) NOT NULL DEFAULT '' AFTER slug;

UPDATE shops SET nr_kuerzel = 'BIO' WHERE slug = 'bio-wolle';
UPDATE shops SET nr_kuerzel = 'SOW' WHERE slug = 'sockenwolle';

ALTER TABLE dokument_nummern
    ADD COLUMN kuerzel VARCHAR(5) NOT NULL DEFAULT '' AFTER typ,
    DROP INDEX uk_dok_typ_jahr,
    ADD UNIQUE `uk_dok_typ_kuerzel_jahr` (`typ`, `kuerzel`, `jahr`);
