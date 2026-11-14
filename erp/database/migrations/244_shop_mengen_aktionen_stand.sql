-- 244: Mengen-Aktionen an die Shops übertragen (NAHTLOS Connector ab 1.7.0) – Stand je Shop (Jacky 2026-11-14)
-- Das ERP schickt die komplette Liste nur, wenn sie sich geändert hat (Prüfsumme mengen_hash):
-- beim Speichern einer Aktion und im Cron (shop_sync.php), damit auch neue Artikel in einer
-- Aktions-Kategorie oder neu verknüpfte Woo-Produkte ankommen. Den Zeitraum prüft der Shop selbst.
-- Wiederholbar (IF NOT EXISTS)

ALTER TABLE shops
    ADD COLUMN IF NOT EXISTS mengen_hash CHAR(32) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS mengen_uebertragen_am DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS mengen_fehler VARCHAR(500) NULL DEFAULT NULL;
