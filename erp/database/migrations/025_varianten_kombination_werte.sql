CREATE TABLE varianten_kombination_werte (
    kombination_id INT UNSIGNED NOT NULL,
    wert_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (kombination_id, wert_id),
    CONSTRAINT fk_varKombiWert_kombination_id
    FOREIGN KEY (kombination_id) REFERENCES artikel (id) ON UPDATE CASCADE,
    CONSTRAINT fk_varKombiWert_wert_id
    FOREIGN KEY (wert_id) REFERENCES varianten_achse_werte (id) ON UPDATE CASCADE
);