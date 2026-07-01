-- Migration 096: Artikelgruppen (Warengruppen mit Kontozuordnung)

CREATE TABLE IF NOT EXISTS artikel_gruppen (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    konto_nr   VARCHAR(10)  NOT NULL,
    name       VARCHAR(100) NOT NULL,
    aktiv      TINYINT(1)   NOT NULL DEFAULT 1,
    sortierung INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_konto_nr (konto_nr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO artikel_gruppen (konto_nr, name, sortierung) VALUES
('4000', 'Wolle',                    10),
('4100', 'Nadeln',                   20),
('4200', 'Stoffe Nähen',             30),
('4250', 'Knöpfe',                   40),
('4300', 'Sticken',                  50),
('4350', 'Spinnen/Weben',            60),
('4400', 'Sonstiges Zubehör',        70),
('4500', 'Hefte Bücher Anleitungen', 80),
('4600', 'Verkaufshilfen',           90),
('4700', 'Gutscheine',              100),
('4900', 'Versandkosten',           110);

-- FK an artikel (NULL = noch nicht zugeordnet)
ALTER TABLE artikel
    ADD COLUMN artikel_gruppe_id INT UNSIGNED NULL AFTER steuerklasse_id,
    ADD CONSTRAINT fk_art_gruppe
        FOREIGN KEY (artikel_gruppe_id) REFERENCES artikel_gruppen(id)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- FK an versandklassen
ALTER TABLE versandklassen
    ADD COLUMN artikel_gruppe_id INT UNSIGNED NULL,
    ADD CONSTRAINT fk_vsk_gruppe
        FOREIGN KEY (artikel_gruppe_id) REFERENCES artikel_gruppen(id)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- Bestehende Versandklassen → 4900 Versandkosten
UPDATE versandklassen
SET artikel_gruppe_id = (SELECT id FROM artikel_gruppen WHERE konto_nr = '4900');
