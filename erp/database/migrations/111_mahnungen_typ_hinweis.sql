-- cron/mahnwesen.php will für überfällige RECHNUNG-Zahler (kein Auto-Storno, nur
-- Hinweis) einen mahnungen-Eintrag vom Typ 'hinweis' anlegen — Enum hatte diesen
-- Wert noch nicht (nur 'erinnerung','stornierung'), dadurch schlug der INSERT fehl.
ALTER TABLE mahnungen MODIFY COLUMN typ ENUM('erinnerung','stornierung','hinweis') NOT NULL;
