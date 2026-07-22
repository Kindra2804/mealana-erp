-- Hersteller-Umbenennung/Adress-Update-Sync: gleiches Muster wie Migration 149
-- bei den Kategorien -- hersteller.aktualisiert_am existiert schon, hier fehlt
-- nur noch der Synced-Zeitstempel auf der Zuweisungsseite.
ALTER TABLE hersteller_shops
    ADD COLUMN synced_at TIMESTAMP NULL AFTER externe_term_id;
