-- Migration 101: Nullbelege (BFR) — monatliche Absicherung + manueller Trigger

CREATE TABLE bfr_nullbelege (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kasse_id         INT UNSIGNED NOT NULL,
    monat            CHAR(7) NOT NULL,
    beleg_nr         VARCHAR(50) NOT NULL,
    ausgeloest_durch ENUM('manuell','automatisch') NOT NULL,
    bfr_status       ENUM('signiert','ausstehend','fehler') NOT NULL DEFAULT 'ausstehend',
    rksv_signatur    VARCHAR(255) NULL,
    rksv_qr          VARCHAR(500) NULL,
    benutzer_id      INT UNSIGNED NULL,
    erstellt_am      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    signiert_am      DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_nullbeleg_kasse_monat (kasse_id, monat),
    CONSTRAINT fk_nullbeleg_kasse FOREIGN KEY (kasse_id) REFERENCES kassen (id) ON UPDATE CASCADE,
    CONSTRAINT fk_nullbeleg_benutzer FOREIGN KEY (benutzer_id) REFERENCES benutzer (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
