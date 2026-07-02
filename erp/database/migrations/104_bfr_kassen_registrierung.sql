-- Migration 104: BFR-Kassen-Registrierung (Protokoll/Backup der FinanzOnline-Meldung)

ALTER TABLE kassen
    ADD COLUMN bfr_aktiv_seit DATETIME NULL AFTER bfr_umsatzzaehler;

CREATE TABLE bfr_kassen_registrierungen (
    id                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kasse_id                  INT UNSIGNED NOT NULL,
    rksv_kassen_id            VARCHAR(50) NOT NULL,
    bfr_url                   VARCHAR(255) NOT NULL,
    uid_nummer                VARCHAR(30) NULL,
    vertrauensdiensteanbieter VARCHAR(50) NULL,
    zertifikat_seriennr_dez   VARCHAR(50) NULL,
    zertifikat_seriennr_hex   VARCHAR(50) NULL,
    zertifikat_gemeldet_am    DATETIME NULL,
    kasse_gemeldet_am         DATETIME NULL,
    startbeleg_geprueft_am    DATETIME NULL,
    startbeleg_inhalt         TEXT NULL,
    abgeschlossen_am          DATETIME NULL,
    benutzer_id               INT UNSIGNED NULL,
    erstellt_am               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bfrreg_kasse (kasse_id),
    CONSTRAINT fk_bfrreg_kasse FOREIGN KEY (kasse_id) REFERENCES kassen (id) ON UPDATE CASCADE,
    CONSTRAINT fk_bfrreg_benutzer FOREIGN KEY (benutzer_id) REFERENCES benutzer (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
