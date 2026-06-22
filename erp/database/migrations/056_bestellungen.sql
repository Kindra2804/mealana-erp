-- Migration 056: Bestellungen (Purchase Order Kopf)
-- Status-Workflow: entwurf → offen → teilgeliefert → erledigt / storniert
-- gutschrift_betrag/notiz für DROPS-Modell (Vorkasse, kein Nachliefern, Gutschrift auf nächste Rechnung)

CREATE TABLE bestellungen (
    id                INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    lieferant_id      INT UNSIGNED    NOT NULL,
    status            ENUM('entwurf','offen','teilgeliefert','erledigt','storniert')
                                      NOT NULL DEFAULT 'entwurf',
    bestelldatum      DATE            NOT NULL,
    erwartet_am       DATE            NULL,
    lieferzeit_text   VARCHAR(100)    NULL        COMMENT 'Freitext z.B. "ab KW38", überschreibt lieferzeit_tage aus artikel_lieferanten',
    ab_nummer         VARCHAR(100)    NULL        COMMENT 'Auftragsbestätigungs-Nummer vom Lieferanten',
    zahlungsart       VARCHAR(50)     NULL        COMMENT 'vorkasse | rechnung | lastschrift',
    ls_nummer         VARCHAR(100)    NULL        COMMENT 'Lieferschein-Nummer vom Lieferanten',
    rechnung_nummer   VARCHAR(100)    NULL,
    rechnung_betrag   DECIMAL(10,2)   NULL,
    rechnung_datum    DATE            NULL,
    gutschrift_betrag DECIMAL(10,2)   NULL        COMMENT 'Offenes Guthaben aus gestrichenen Positionen (DROPS-Modell)',
    gutschrift_notiz  TEXT            NULL,
    notiz             TEXT            NULL,
    benutzer_id       INT UNSIGNED    NOT NULL,
    erstellt_am       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    geaendert_am      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_best_lieferant FOREIGN KEY (lieferant_id) REFERENCES lieferanten (id) ON UPDATE CASCADE,
    CONSTRAINT fk_best_benutzer  FOREIGN KEY (benutzer_id)  REFERENCES benutzer (id)    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
