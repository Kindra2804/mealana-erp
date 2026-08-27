-- Tabelle zur Auswertung für Konfigurationsartikel und deren Werte

CREATE TABLE position_konfiguration ( 
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    referenz_tabelle VARCHAR(50) NOT NULL,
    referenz_id INT UNSIGNED NOT NULL,
    achse_id INT UNSIGNED NOT NULL,
    wert_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_posKonfig_achse FOREIGN KEY (achse_id) REFERENCES varianten_achsen(id),
    CONSTRAINT fk_posKonfig_wert FOREIGN KEY (wert_id) REFERENCES varianten_achse_werte(id)
);