# CREATE TABLE Tabellen anlegen

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
    geaendert_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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


# ALTER TABLE - FOREIGN KEYS bestimmen

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

ALTER TABLE artikel_lieferanten
    ADD CONSTRAINT fk_artlief_artikel_id
    FOREIGN KEY (artikel_id)
    REFERENCES artikel(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;
    
ALTER TABLE artikel_lieferanten
    ADD CONSTRAINT fk_artlief_lieferant_id
    FOREIGN KEY (lieferant_id)
    REFERENCES lieferanten(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;
    
ALTER TABLE lieferanten_vertreter
    ADD CONSTRAINT fk_vertreter_lieferant_id
    FOREIGN KEY (lieferant_id)
    REFERENCES lieferanten(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;
    
fk_{tabellenname_kurz}_{spaltenname}

fk_artlief_artikel_id    ✅
fk_artlief_lieferant_id  ✅
fk_vertreter_lieferant_id ✅

# SQL ABFRAGEN JOINS

SELECT 
    v.farbe_name,
    l.name AS lager,
    lb.charge,
    lb.charge_status,
    lb.bestand
FROM lagerbestand lb
LEFT JOIN artikel_varianten v ON lb.artikel_varianten_id = v.id
LEFT JOIN lager l ON lb.lager_id = l.id
ORDER BY v.farbe_name, l.name

  # "Zeig alle Merkmale von Artikel 1 mit Gruppenname, Merkmalname, Einheit und Wert"

SELECT 
    artikel.name AS Artikelname,
    merkmale.name AS MerkmalName,
    merkmale.einheit,
    merkmal_gruppen.name AS GruppenName,
    artikel_merkmale.wert_bool,
    artikel_merkmale.wert_text,
    artikel_merkmale.wert_zahl
FROM artikel
LEFT JOIN artikel_merkmale ON artikel.id = artikel_merkmale.artikel_id
LEFT JOIN merkmale ON artikel_merkmale.merkmal_id = merkmale.id
LEFT JOIN merkmal_gruppen ON merkmale.merkmal_gruppen_id = merkmal_gruppen.id
WHERE artikel.id = 1


# desribe

DESCRIBE artikel;