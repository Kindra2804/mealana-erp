-- Migration 105: Jarvis-Systembenutzer als Standard-Seed
-- Wird von Logger::log() ueberall dort gebraucht, wo ohne Session geloggt wird
-- (Cronjobs, BfrService-Nachsignierung). Passwort '!' ist absichtlich kein
-- gueltiger bcrypt-Hash -> password_verify() liefert dafuer immer false,
-- der Account kann sich also nie einloggen. Keine feste ID noetig: ueberall
-- im Code wird Jarvis per username='system' nachgeschlagen (siehe
-- LagerService::getJarvisId(), BfrService::getJarvisId()).
INSERT IGNORE INTO benutzer (username, passwort, formularname, aktiv)
VALUES ('system', '!', 'Jarvis (System)', 1);
