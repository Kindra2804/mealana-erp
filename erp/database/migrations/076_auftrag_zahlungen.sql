CREATE TABLE auftrag_zahlungen (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auftrag_id    INT UNSIGNED     NOT NULL,
    betrag        DECIMAL(10,2)    NOT NULL,
    buchungsdatum DATE             NOT NULL,
    notiz         VARCHAR(255)     NULL,
    erfasst_am    TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
    erfasst_von   INT UNSIGNED     NULL,
    CONSTRAINT fk_aufzahl_auftrag   FOREIGN KEY (auftrag_id)  REFERENCES auftraege(id) ON DELETE CASCADE,
    CONSTRAINT fk_aufzahl_benutzer  FOREIGN KEY (erfasst_von) REFERENCES benutzer(id)  ON DELETE SET NULL
);
