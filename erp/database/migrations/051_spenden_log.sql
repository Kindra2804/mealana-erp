-- Spenden-Log (Yarnpride und ähnliche Organisationen)
--
-- Wenn ein Partner vom Typ 'spende' oder 'beides' Artikel über MeaLana vertreibt
-- und Kunden dafür eine Spende leisten, wird die Spende hier dokumentiert.
-- Das Geld ist kein MeaLana-Umsatz (Durchlaufposten) → Klärung mit Steuerberater nötig.
--
-- weitergeleitet: 0 = noch offen, 1 = Spende an Organisation überwiesen.
-- Spender-Name ist optional (Datenschutz).

CREATE TABLE spenden_log (
    id                  INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    partner_id          INT UNSIGNED    NOT NULL,
    artikel_id          INT UNSIGNED    NOT NULL,
    spender_name        VARCHAR(100)    NULL,           -- optional (Datenschutz)
    betrag              DECIMAL(10,2)   NOT NULL,
    datum               TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    kassen_beleg_nr     VARCHAR(50)     NULL,
    weitergeleitet      TINYINT(1)      NOT NULL DEFAULT 0,
    weitergeleitet_am   DATE            NULL,

    CONSTRAINT fk_splog_partner FOREIGN KEY (partner_id) REFERENCES partner(id)  ON DELETE RESTRICT,
    CONSTRAINT fk_splog_artikel FOREIGN KEY (artikel_id) REFERENCES artikel(id)  ON DELETE RESTRICT,
    INDEX idx_splog_partner     (partner_id),
    INDEX idx_splog_datum       (datum),
    INDEX idx_splog_weitergeleitet (weitergeleitet)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
