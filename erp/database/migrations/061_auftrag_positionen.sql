-- Migration 061: Auftragspositionen
-- charge als VARCHAR (analog lagerbestand.charge) — kein eigenes Chargen-FK-System
-- bezeichnung + ean eingefroren zum Auftragszeitpunkt (Artikel kann später geändert werden)
-- menge_geliefert für Teillieferungs-Tracking (Fehlbestand-Flow)

CREATE TABLE auftrag_positionen (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    auftrag_id        INT UNSIGNED  NOT NULL,
    artikel_id        INT UNSIGNED  NOT NULL,

    charge            VARCHAR(20)   NULL,         -- Farbkonsistenz-Tracking
    bezeichnung       VARCHAR(255)  NOT NULL,     -- eingefroren
    ean               VARCHAR(20)   NULL,          -- eingefroren
    menge             INT UNSIGNED  NOT NULL,
    menge_geliefert   INT UNSIGNED  NOT NULL DEFAULT 0,

    einzelpreis_netto DECIMAL(10,4) NOT NULL,
    steuer_prozent    DECIMAL(5,2)  NOT NULL DEFAULT 20.00,
    rabatt_prozent    DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    gesamtpreis_netto DECIMAL(10,2) NOT NULL,

    sort_order        INT UNSIGNED  NOT NULL DEFAULT 0,

    CONSTRAINT fk_aufpos_auftrag FOREIGN KEY (auftrag_id) REFERENCES auftraege (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_aufpos_artikel FOREIGN KEY (artikel_id) REFERENCES artikel (id)   ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
