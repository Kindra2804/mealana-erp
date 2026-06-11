CREATE TABLE reservierungen (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    artikel_id INT UNSIGNED NOT NULL,
    lager_id INT UNSIGNED NOT NULL DEFAULT 1,
    menge INT UNSIGNED NOT NULL,
    kanal VARCHAR(30) NOT NULL,
    referenz_tabelle VARCHAR(50) NULL,
    referenz_id INT UNSIGNED NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'offen',
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    geaendert_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
);

ALTER TABLE reservierungen 
    ADD CONSTRAINT fk_reservierungen_artikel_id
    FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE;

ALTER TABLE reservierungen 
    ADD CONSTRAINT fk_reservierungen_lager_id
    FOREIGN KEY (`lager_id`) REFERENCES `lager` (`id`) ON UPDATE CASCADE;
 


