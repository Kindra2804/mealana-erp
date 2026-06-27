-- Migration 086: lieferanten_zugaenge – Händlerportal-Zugänge pro Lieferant
-- Passwort AES-256-GCM verschlüsselt (wie Kundendaten), BLOB-Spalte

CREATE TABLE lieferanten_zugaenge (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    lieferant_id    INT UNSIGNED    NOT NULL,
    bezeichnung     VARCHAR(100)    NOT NULL    COMMENT 'z.B. Bestellportal, Händlerlogin, FTP',
    url             VARCHAR(500)    NULL,
    benutzername    VARCHAR(255)    NULL,
    passwort_enc    BLOB            NULL        COMMENT 'AES-256-GCM verschlüsselt',
    notizen         TEXT            NULL,
    aktiv           TINYINT(1)      NOT NULL DEFAULT 1,
    erstellt_am     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    geaendert_am    TIMESTAMP       NULL ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_lzug_lieferant FOREIGN KEY (lieferant_id) REFERENCES lieferanten (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
