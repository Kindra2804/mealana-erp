-- Migration 058: Bestellung Eingänge (Wareneingang-Buchungen gegen Bestellpositionen)
-- Ein Eingang = ein EAN-Scan mit Mengenvorwahl
-- bewegung_id verknüpft mit lager_bewegungen (Lager-Buchung bleibt immutable)
-- charge: Snapshot der gewählten/neu angelegten Charge zum Eingangs-Zeitpunkt

CREATE TABLE bestellung_eingaenge (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    position_id   INT UNSIGNED    NOT NULL,
    bewegung_id   INT UNSIGNED    NULL        COMMENT 'FK auf lager_bewegungen — NULL wenn Charge "zu erfassen"',
    menge         DECIMAL(10,3)   NOT NULL,
    charge        VARCHAR(100)    NULL,
    lager_id      INT UNSIGNED    NOT NULL,
    benutzer_id   INT UNSIGNED    NOT NULL,
    erstellt_am   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_bein_position FOREIGN KEY (position_id) REFERENCES bestellung_positionen (id) ON UPDATE CASCADE,
    CONSTRAINT fk_bein_bewegung FOREIGN KEY (bewegung_id) REFERENCES lager_bewegungen (id)      ON UPDATE CASCADE,
    CONSTRAINT fk_bein_lager    FOREIGN KEY (lager_id)    REFERENCES lager (id)                 ON UPDATE CASCADE,
    CONSTRAINT fk_bein_benutzer FOREIGN KEY (benutzer_id) REFERENCES benutzer (id)              ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
