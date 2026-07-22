-- Kategorie-Umbenennung/Update-Sync: bisher wurde eine Kategorie nur EINMAL
-- angelegt, spätere Namens-/Beschreibungs-/Oberkategorie-Änderungen zogen nie
-- nach WooCommerce nach. Change-Detection analog zu artikel/artikel_shops.
ALTER TABLE kategorien
    ADD COLUMN aktualisiert_am TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP AFTER beschreibung;

ALTER TABLE kategorie_shops
    ADD COLUMN synced_at TIMESTAMP NULL AFTER externe_kategorie_id;
