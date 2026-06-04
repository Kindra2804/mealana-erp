CREATE TABLE artikel_externe_referenzen (
    id INT UNSIGNED AUTO_INCREMENT,
    artikel_id INT UNSIGNED, 
    datenquelle VARCHAR(50) NOT NULL,
    externe_id VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_aer_artikel_id FOREIGN KEY (artikel_id)
    REFERENCES artikel(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
    UNIQUE KEY uq_datenquelle_externe_id (datenquelle, externe_id)
);

CREATE TABLE kategorien (
    id INT UNSIGNED AUTO_INCREMENT,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    sortierung INT UNSIGNED NOT NULL DEFAULT 0,
    aktiv TINYINT(1) NOT NULL DEFAULT 1,
    externe_id VARCHAR(100) NULL,
    datenquelle VARCHAR(50) NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_kat_parent_id FOREIGN KEY (parent_id)
    REFERENCES kategorien(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE TABLE artikel_kategorien (
    artikel_id INT UNSIGNED,
    kategorie_id INT UNSIGNED,
    PRIMARY KEY (artikel_id, kategorie_id),
    CONSTRAINT fk_ak_artikel_id FOREIGN KEY (artikel_id)
    REFERENCES artikel(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
    CONSTRAINT fk_ak_kategorie_id FOREIGN KEY (kategorie_id)
    REFERENCES kategorien(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);