-- Phase 3 Online-Shop-Anbindung: Polling-Cursor für Bestellungen-Sync.
-- Bewusst reines Polling statt Webhook (siehe project_shop_sync.md) --
-- WooCommerce kann unser VPN-only-ERP nicht per Push erreichen.
ALTER TABLE shops
    ADD COLUMN bestellungen_letzter_sync TIMESTAMP NULL AFTER wp_app_password;
