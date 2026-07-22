-- Sperre analog zum JTL-Komplettabgleich: der laufende 15-Minuten-Cron
-- (cron/shop_sync.php) und ein manueller Bulk-Import (scripts/erstbefuellung_bilder.php)
-- dürfen für denselben Shop nicht gleichzeitig laufen (Race Condition: doppelter
-- Bild-Upload, oder der Cron überschreibt mit veraltetem Bilderstand).
ALTER TABLE shops
    ADD COLUMN bulk_import_aktiv TINYINT(1) NOT NULL DEFAULT 0 AFTER wp_app_password;
