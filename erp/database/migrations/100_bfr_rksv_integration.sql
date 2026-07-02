-- Migration 100: BFR/RKSV-Signatur-Integration

CREATE TABLE bfr_nachsignierungs_laeufe (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kasse_id               INT UNSIGNED NOT NULL,
    ausgeloest_durch       ENUM('automatisch','cronjob','manuell') NOT NULL,
    gestartet_am           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    beendet_am             DATETIME NULL,
    anzahl_signiert        INT UNSIGNED NOT NULL DEFAULT 0,
    anzahl_fehlgeschlagen  INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_bfrlauf_kasse (kasse_id),
    CONSTRAINT fk_bfrlauf_kasse FOREIGN KEY (kasse_id) REFERENCES kassen (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE kassen
    ADD COLUMN bfr_url VARCHAR(255) NULL AFTER rksv_kassen_id;

ALTER TABLE kassen_bons
    ADD COLUMN steuer_a               DECIMAL(10,2) NULL AFTER rksv_qr,
    ADD COLUMN steuer_b               DECIMAL(10,2) NULL AFTER steuer_a,
    ADD COLUMN steuer_c               DECIMAL(10,2) NULL AFTER steuer_b,
    ADD COLUMN steuer_d               DECIMAL(10,2) NULL AFTER steuer_c,
    ADD COLUMN steuer_e               DECIMAL(10,2) NULL AFTER steuer_d,
    ADD COLUMN bfr_status             ENUM('signiert','ausstehend','fehler') NOT NULL DEFAULT 'ausstehend' AFTER steuer_e,
    ADD COLUMN signiert_am            DATETIME NULL AFTER bfr_status,
    ADD COLUMN nachsignierungs_lauf_id INT UNSIGNED NULL AFTER signiert_am,
    ADD CONSTRAINT fk_bon_bfrlauf FOREIGN KEY (nachsignierungs_lauf_id) REFERENCES bfr_nachsignierungs_laeufe (id) ON UPDATE CASCADE ON DELETE SET NULL;
