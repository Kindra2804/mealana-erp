-- Gutschein-Modul Schritt C: jeder Gutschein (egal ob Kasse, ERP oder Shop) wird
-- als Germanized-"Wertgutschein" (is_voucher + Versand inklusive) in den Onlineshop
-- gespiegelt -- nicht mehr direkt beim Erstellen (hätte die Kasse bei langsamer/
-- fehlender Internetverbindung blockiert), sondern über den Shop-Sync-Cron: jede
-- Wertänderung (Ausstellung, Einlösung, Storno) setzt nur dieses Flag.
ALTER TABLE gutscheine
    ADD COLUMN woo_sync_faellig TINYINT(1) NOT NULL DEFAULT 0 AFTER woo_coupon_id,
    ADD INDEX idx_gutschein_woo_sync (woo_sync_faellig);

-- Bestehende einlösbare Gutscheine einmalig spiegeln
UPDATE gutscheine SET woo_sync_faellig = 1
WHERE status = 'aktiv' AND restguthaben > 0;

-- In welchem Shop Gutscheine ohne eigene Shop-Zuordnung (Kasse, ERP) online
-- einlösbar sind. Bewusst EIN Shop: derselbe Code in mehreren WooCommerce-Shops
-- könnte zwischen zwei Sync-Läufen in jedem Shop einmal eingelöst werden.
INSERT INTO system_einstellungen (schluessel, wert) VALUES ('gutschein_shop_id', '1');
