CREATE TABLE lager (
    id INT UNSIGNED AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    typ VARCHAR(50) NOT NULL,
    aktiv TINYINT(1),
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id))
CREATE TABLE lagerbestand (
    id  INT UNSIGNED AUTO_INCREMENT,
    artikel_varianten_id INT UNSIGNED,
    lager_id INT UNSIGNED,
    charge VARCHAR(20),
    charge_status ENUM('erfasst', 'unbekannt', 'nachzutragen') DEFAULT 'unbekannt'
    bestand INT UNSIGNED,
    mindestbestand INT UNSIGNED,
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    geaendert_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON CHANGE CURRENT_TIMESTAMP,
    PRIMARY KEY (id))

    CREATE TABLE merkmal_gruppen (
    id INT UNSIGNED AUTO_INCREMENT, 
    name VARCHAR(50) NOT NULL, 
    aktiv TINYINT(1), 
    erstellt_am  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

CREATE TABLE merkmale (
    id INT UNSIGNED AUTO_INCREMENT,
    merkmal_gruppen_id INT UNSIGNED,
    name VARCHAR(50) NOT NULL, 
    einheit VARCHAR(50) NOT NULL,
    datentyp ENUM('text','zahl','bool'),
    filterbar TINYINT(1),
    aktiv TINYINT(1),
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

CREATE TABLE artikel_merkmale (
    id INT UNSIGNED AUTO_INCREMENT,
    artikel_id INT UNSIGNED, 
    merkmal_id INT UNSIGNED,
    wert_text VARCHAR(255),
    wert_zahl DECIMAL(8,2),
    wert_bool TINYINT(1),
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

ALTER TABLE lagerbestand
    ADD CONSTRAINT fk_artikel_varianten
    FOREIGN KEY (artikel_varianten)
    REFERENCES artikel_varianten(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

ALTER TABLE lagerbestand
    ADD CONSTRAINT fk_lager_id
    FOREIGN KEY (lager_id)
    REFERENCES lager(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

ALTER TABLE artikel_merkmale
    ADD CONSTRAINT fk_artikel_id
    FOREIGN KEY (artikel_id)
    REFERENCES artikel(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

ALTER TABLE artikel_merkmale
    ADD CONSTRAINT fk_merkmal_id
    FOREIGN KEY (merkmal_id)
    REFERENCES merkmale(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;
    
ALTER TABLE merkmale
    ADD CONSTRAINT fk_merkmal_gruppen_id
    FOREIGN KEY (merkmal_gruppen_id)
    REFERENCES merkmal_gruppen(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;