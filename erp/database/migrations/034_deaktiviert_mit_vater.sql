ALTER TABLE artikel
    ADD COLUMN deaktiviert_mit_vater TINYINT(1) NOT NULL DEFAULT 0
    AFTER aktiv;