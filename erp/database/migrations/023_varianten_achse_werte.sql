CREATE TABLE varianten_achse_werte (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    artikel_id INT UNSIGNED NOT NULL,
    achse_id INT UNSIGNED NOT NULL,
    wert VARCHAR(100) NOT NULL,
    wert_zusatz VARCHAR(100) NULL,
    aufpreis DECIMAL(10,2) DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CONSTRAINT fk_varAchsWert_artikel
    FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON UPDATE CASCADE,
    CONSTRAINT fk_varAchsWert_achse
    FOREIGN KEY (achse_id) REFERENCES varianten_achsen (id) ON UPDATE CASCADE
);