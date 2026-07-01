-- Migration 095: Kassen-Abschlüsse (X-Bon / Z-Bon) mit Detaildaten

CREATE TABLE IF NOT EXISTS kassen_abschluesse (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bon_id        INT UNSIGNED NOT NULL,
    kasse_id      INT UNSIGNED NOT NULL,
    kassierer_id  INT UNSIGNED NULL,
    typ           ENUM('x','z') NOT NULL,
    datum         DATE         NOT NULL,
    kassenstand   DECIMAL(10,2) NOT NULL DEFAULT 0,
    daten         JSON         NOT NULL COMMENT 'Kennzahlen, Steueraufstellung, Kassenbuch, Bon-Range',
    erstellt_am   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kasse_datum (kasse_id, datum),
    KEY idx_typ (typ),
    CONSTRAINT fk_ka_bon    FOREIGN KEY (bon_id)       REFERENCES kassen_bons(id),
    CONSTRAINT fk_ka_kasse  FOREIGN KEY (kasse_id)     REFERENCES kassen(id),
    CONSTRAINT fk_ka_kass2  FOREIGN KEY (kassierer_id) REFERENCES benutzer(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
