-- 253: Newsletter Schritt 5 — Segmente (Jacky 2026-11-15: bauen, aber erst später anbieten)
-- segment = JSON mit Bedingungen (alle müssen zutreffen, leer = egal), siehe NewsletterSegmentService.
-- Sichtbar erst, wenn newsletter_segmente_aktiv = 1 (Häkchen in Kunden → Newsletter verfassen).
-- Wiederholbar.

ALTER TABLE newsletter
    ADD COLUMN IF NOT EXISTS segment TEXT NULL AFTER bausteine;

INSERT IGNORE INTO system_einstellungen (schluessel, wert) VALUES ('newsletter_segmente_aktiv', '0');
