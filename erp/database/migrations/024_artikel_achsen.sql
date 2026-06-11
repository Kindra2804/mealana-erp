CREATE TABLE artikel_achsen (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    artikel_id INT UNSIGNED NOT NULL,
    achse_id INT UNSIGNED NOT NULL,
    bedingungs_achse_id INT UNSIGNED NULL,
    bedingungs_wert_id INT UNSIGNED NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CONSTRAINT fk_artAchs_artikel_id
    FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON UPDATE CASCADE,
    CONSTRAINT fk_artAchs_achse_id
    FOREIGN KEY (achse_id) REFERENCES varianten_achsen (id) ON UPDATE CASCADE,
    CONSTRAINT fk_artAchs_bedingungs_achse_id
    FOREIGN KEY (bedingungs_achse_id) REFERENCES varianten_achsen (id) ON UPDATE CASCADE,
    CONSTRAINT fk_artAchs_bedingungs_wert_id
    FOREIGN KEY (bedingungs_wert_id) REFERENCES varianten_achse_werte (id) ON UPDATE CASCADE
);