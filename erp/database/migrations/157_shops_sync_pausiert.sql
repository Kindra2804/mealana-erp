-- Pause-Schalter für den Shop-Sync-Cron, gesteuert über die neue Web-UI
-- "Shop-Synchronisierung" (Einstellungen). Anderes Konzept als bulk_import_aktiv:
-- bulk_import_aktiv = "gerade läuft ein manueller Bulk-Lauf, Cron soll kurz
-- warten" (wird vom Skript selbst gesetzt/gelöscht). sync_pausiert = "Betreiber
-- will den automatischen Sync für diesen Shop bewusst anhalten" (wird nur vom
-- Menschen über die UI gesetzt, bleibt bis er es wieder aufhebt).
ALTER TABLE shops
    ADD COLUMN sync_pausiert TINYINT(1) NOT NULL DEFAULT 0 AFTER bulk_import_aktiv;
