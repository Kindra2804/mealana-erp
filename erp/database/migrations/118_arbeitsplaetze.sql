-- Arbeitsplätz - als neue Tabelle um Arbeitsplätze eindeutig identifizieren zu können
-- auch für spätere Auto-Logout-Funktionalität und Session-Limits


CREATE TABLE arbeitsplaetze (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(50) NOT NULL,
    geraete_token   VARCHAR(36) NOT NULL UNIQUE,
    typ            ENUM('kasse','lager', 'buero', 'mobil') NOT NULL,
    kasse_id        INT UNSIGNED NULL,
    aktiv           TINYINT(1) NOT NULL DEFAULT 1,
    erstellt_am      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    COnSTRAINT fk_arbeitsplatz_kasse FOREIGN KEY (kasse_id) REFERENCES kassen (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE sessions
    ADD COLUMN arbeitsplatz_id INT UNSIGNED NULL AFTER user_agent,
    ADD COLUMN geraete_token VARCHAR(36) NULL AFTER arbeitsplatz_id,
    ADD CONSTRAINT fk_sessions_arbeitsplatz FOREIGN KEY (arbeitsplatz_id) REFERENCES arbeitsplaetze (id) ON UPDATE CASCADE
;

ALTER TABLE benutzer
    ADD COLUMN max_sessions INT UNSIGNED NOT NULL DEFAULT 0 AFTER aktiv;
    -- wird aktuell nirgends gelesen, gebaut für späteres Lizenzmodul, um die Anzahl gleichzeitiger Sessions pro Benutzer zu limitieren (0 = keine Limitierung)
