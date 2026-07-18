-- Migration 138: Live-Sperre pro Lagerplatz während der Zählung (informativ, kein Hard-Block)
-- Siehe project_inventur_konzept: "first come" — wer zuerst einen Lagerplatz zum
-- Zählen öffnet, wird als aktiver Zähler markiert. Kommt eine zweite Person dazu,
-- wird sie gewarnt, aber nicht blockiert (z.B. falls der erste Zähler abgebrochen hat).
-- zuletzt_aktiv_am verfällt informell nach ~10 Minuten (siehe InventurService).

CREATE TABLE inventur_zaehl_sperren (
    id                INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    inventur_lauf_id  INT UNSIGNED    NOT NULL,
    lagerplatz_id     INT UNSIGNED    NOT NULL,
    benutzer_id       INT UNSIGNED    NOT NULL,
    aktiv_seit        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    zuletzt_aktiv_am  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_invsperre_lauf_platz (inventur_lauf_id, lagerplatz_id),
    CONSTRAINT fk_invsperre_lauf     FOREIGN KEY (inventur_lauf_id) REFERENCES inventur_laeufe (id) ON DELETE CASCADE,
    CONSTRAINT fk_invsperre_platz    FOREIGN KEY (lagerplatz_id)    REFERENCES lagerplaetze (id)    ON UPDATE CASCADE,
    CONSTRAINT fk_invsperre_benutzer FOREIGN KEY (benutzer_id)      REFERENCES benutzer (id)        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
