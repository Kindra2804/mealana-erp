ALTER TABLE aktionen
    ADD COLUMN gestartet TINYINT(1) NOT NULL DEFAULT 0 AFTER beschreibung;
