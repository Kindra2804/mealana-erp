ALTER TABLE varianten_achsen
    ADD COLUMN ist_gruppe TINYINT(1) NOT NULL DEFAULT 0
    AFTER darstellungsform;
