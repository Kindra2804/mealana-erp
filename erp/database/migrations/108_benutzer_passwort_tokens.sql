-- Passwort-Setzen-Link (Admin legt Benutzer an ODER "Passwort vergessen" auf der
-- Login-Seite) — beide Wege nutzen denselben Token-Mechanismus. Token wird nur
-- gehasht gespeichert (wie ein Passwort-Hash): falls die DB abhandenkommt, kann
-- niemand damit direkt Konten übernehmen.
CREATE TABLE benutzer_passwort_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    benutzer_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    ausgestellt_am TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    laeuft_ab_am TIMESTAMP NULL, -- immer explizit beim Insert gesetzt; NULL erlaubt wegen NO_ZERO_DATE sql_mode
    verwendet_am TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY idx_token_hash (token_hash),
    CONSTRAINT fk_bpt_benutzer FOREIGN KEY (benutzer_id) REFERENCES benutzer(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
