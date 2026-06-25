-- Migration 070: mahnungen.typ um 'hinweis' erweitern
-- 'hinweis' = Rechnung 30+ Tage überfällig, manuell prüfen (kein Auto-Storno)

ALTER TABLE mahnungen
    MODIFY COLUMN typ ENUM('erinnerung', 'stornierung', 'hinweis') NOT NULL;
