-- Migration 093: Geparkter Bon persistent in DB (statt sessionStorage)
-- Mehrere geparkte Bons pro Kasse möglich

CREATE TABLE IF NOT EXISTS kassen_geparkte_bons (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kasse_id      INT UNSIGNED NOT NULL,
    kassierer_id  INT UNSIGNED NULL,
    kunden_id     INT UNSIGNED NULL,
    kunden_name   VARCHAR(120) NULL,
    warenkorb     JSON         NOT NULL,
    global_rabatt DECIMAL(5,2) NOT NULL DEFAULT 0,
    auftrag_id    INT UNSIGNED NULL,
    notiz         VARCHAR(255) NULL,
    kontext       JSON         NULL,     -- auftrag_nr, status, zusatzPositionen etc.
    erstellt_am   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kasse (kasse_id),
    CONSTRAINT fk_gpb_kasse  FOREIGN KEY (kasse_id)     REFERENCES kassen(id),
    CONSTRAINT fk_gpb_kass2  FOREIGN KEY (kassierer_id) REFERENCES benutzer(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
