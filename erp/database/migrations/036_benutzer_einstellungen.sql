CREATE TABLE benutzer_einstellungen (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    benutzer_id  INT UNSIGNED NOT NULL,
    schluessel   VARCHAR(100) NOT NULL,
    wert         TEXT NOT NULL,
    geaendert_am TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uq_benutzer_schluessel (benutzer_id, schluessel),
    CONSTRAINT fk_benutzereinst_benutzer FOREIGN KEY (benutzer_id) REFERENCES benutzer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
