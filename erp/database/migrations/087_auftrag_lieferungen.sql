-- Migration 087: Lieferhistory pro Auftrag
-- Für Teillieferungen: jede Lieferung als eigene Zeile statt Überschreiben von tracking_nr

CREATE TABLE IF NOT EXISTS auftrag_lieferungen (
    id                    INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    auftrag_id            INT UNSIGNED      NOT NULL,
    tracking_nr           VARCHAR(100)      NOT NULL,
    versanddienstleister  VARCHAR(50)       DEFAULT NULL,
    versand_datum         DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ist_teillieferung     TINYINT(1)        NOT NULL DEFAULT 0,
    benutzer_id           INT UNSIGNED      DEFAULT NULL,
    erstellt_am           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auftrag_id (auftrag_id),
    CONSTRAINT fk_auftrag_lieferungen_auftrag
        FOREIGN KEY (auftrag_id) REFERENCES auftraege(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
