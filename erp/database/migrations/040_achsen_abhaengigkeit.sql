ALTER TABLE varianten_achsen
    ADD COLUMN abhaengig_von_achse_id INT UNSIGNED NULL AFTER darstellungsform,
    ADD CONSTRAINT fk_varAchsen_abhaengigVon
        FOREIGN KEY (abhaengig_von_achse_id)
        REFERENCES varianten_achsen(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL;
