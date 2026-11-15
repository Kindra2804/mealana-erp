-- 250: Newsletter Schritt 2 — Abgleich mit der Belegseite (beleg.<domain>) für Anmeldung/Bestätigung/Abmeldung
-- portal_stand: wann der Link-Schlüssel dieses Empfängers zuletzt ans Portal ging (geaendert_am > portal_stand → erneut)
-- doi_gesendet_am: letzte Bestätigungs-Mail (Double-Opt-in), damit sie bei Doppelanmeldung nicht zu oft kommt
-- shops.website_url / design_farbe: Adresse + Hausfarbe des Shops für Portal-Seiten und Newsletter
--   (Shops ohne WooCommerce, z.B. noch auf JTL, haben keine wc_url). Gepflegt unter Einstellungen → Kanäle.
-- Wiederholbar.

ALTER TABLE newsletter_empfaenger
    ADD COLUMN IF NOT EXISTS portal_stand    DATETIME NULL DEFAULT NULL AFTER token,
    ADD COLUMN IF NOT EXISTS doi_gesendet_am DATETIME NULL DEFAULT NULL AFTER portal_stand;

ALTER TABLE shops
    ADD COLUMN IF NOT EXISTS website_url  VARCHAR(255) NULL DEFAULT NULL AFTER wc_url,
    ADD COLUMN IF NOT EXISTS design_farbe VARCHAR(20)  NULL DEFAULT NULL AFTER website_url;
