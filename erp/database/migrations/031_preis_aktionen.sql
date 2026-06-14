CREATE TABLE preis_aktionen (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    typ         ENUM('sale', 'lieferant_aktion') NOT NULL DEFAULT 'sale',
    gueltig_ab  DATETIME NOT NULL,
    gueltig_bis DATETIME NULL,
    aktiv       TINYINT(1) NOT NULL DEFAULT 1,
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

CREATE TABLE preis_aktionen_positionen (
    id                   INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    aktion_id            INT UNSIGNED   NOT NULL,
    artikel_id           INT UNSIGNED   NOT NULL,
    kundengruppen_id     INT UNSIGNED   NULL,
    brutto_vk            DECIMAL(8,2)   NOT NULL,
    netto_vk             DECIMAL(8,2)   NOT NULL,
    preis_vorher_brutto  DECIMAL(8,2)   NULL,
    erstellt_am          TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_prAktPos_aktion_id
        FOREIGN KEY (aktion_id)        REFERENCES preis_aktionen (id) ON UPDATE CASCADE,
    CONSTRAINT fk_prAktPos_artikel_id
        FOREIGN KEY (artikel_id)       REFERENCES artikel (id) ON UPDATE CASCADE,
    CONSTRAINT fk_prAktPos_kg_id
        FOREIGN KEY (kundengruppen_id) REFERENCES kundengruppen (id) ON UPDATE CASCADE
);