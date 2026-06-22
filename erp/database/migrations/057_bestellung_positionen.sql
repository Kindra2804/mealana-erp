-- Migration 057: Bestellung Positionen (Zeilenpositionen einer PO)
-- artikel_id zeigt auf Vater- ODER Kind-Artikel (beide sind artikel-Einträge)
-- gestrichen=1 wenn bei Teillieferung "Rest streichen" gewählt wurde
-- ek_preis: Snapshot zum Bestellzeitpunkt (artikel_lieferanten.netto_ek kann sich ändern)

CREATE TABLE bestellung_positionen (
    id                INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    bestellung_id     INT UNSIGNED    NOT NULL,
    artikel_id        INT UNSIGNED    NOT NULL,
    menge_bestellt    DECIMAL(10,3)   NOT NULL,
    menge_eingegangen DECIMAL(10,3)   NOT NULL DEFAULT 0,
    ek_preis          DECIMAL(10,4)   NULL        COMMENT 'EK-Preis-Snapshot zum Bestellzeitpunkt',
    gestrichen        TINYINT(1)      NOT NULL DEFAULT 0,
    notiz             VARCHAR(255)    NULL,

    CONSTRAINT fk_bpos_bestellung FOREIGN KEY (bestellung_id) REFERENCES bestellungen (id) ON UPDATE CASCADE,
    CONSTRAINT fk_bpos_artikel    FOREIGN KEY (artikel_id)    REFERENCES artikel (id)       ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
