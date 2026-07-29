-- Kategoriebild: WooCommerce zeigt pro Produktkategorie ein Thumbnail (Mega-Menü,
-- Kategorie-Grid, Kategorieseite selbst) -- dafür gab es bisher kein Feld.
ALTER TABLE kategorien
    ADD COLUMN bild_pfad VARCHAR(255) NULL AFTER beschreibung;

-- Sync-Tracking analog zu externe_kategorie_id/synced_at: welcher Dateiname wurde
-- zuletzt zu diesem Shop hochgeladen (Change-Detection, kein Re-Upload bei jedem
-- Cron-Lauf) und unter welcher WordPress-Medien-ID liegt er dort.
ALTER TABLE kategorie_shops
    ADD COLUMN bild_pfad_synced VARCHAR(255) NULL AFTER synced_at,
    ADD COLUMN bild_external_id VARCHAR(64) NULL AFTER bild_pfad_synced;
