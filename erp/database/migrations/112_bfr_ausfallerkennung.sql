-- Migration 112: BFR-Ausfallerkennung ersetzt die alte Nachsignierungs-Warteschlange
--
-- Hintergrund: Der reale BFR-Hardware-Test hat ein Reihenfolge-Risiko der alten
-- Logik aufgedeckt ("BFR nicht erreichbar -> Beleg trotzdem anlegen, bfr_status=
-- 'ausstehend', später nachsignieren"): sobald ein Beleg dabei in den Zustand
-- 'fehler' geriet, fiel er aus der Nachsignierungs-Abfrage raus, und spaetere
-- Belege konnten an ihm vorbeiziehen - RKSV verlangt aber eine strikt aufsteigende
-- Belegfolge beim BFR.
--
-- Neues Modell: VOR jeder Bon-/Storno-/Nullbeleg-Erstellung wird /state geprüft
-- (mit Retry-Popup an der Kasse) - ist der Dienst nicht erreichbar, wird der
-- Verkauf gar nicht erst zugelassen ("kein Dienst, keine Kasse", Empfehlung des
-- BFR-Herstellers). Ist der Dienst erreichbar, kommt von /register laut Hersteller-
-- API IMMER eine Antwort (RC ist immer "OK", nur <Link> unterscheidet "echte
-- Signatur" von "Sicherheitseinrichtung ausgefallen") - beide Fälle sind damit
-- sofort abgeschlossen, ein Beleg bleibt nie mehr unsigniert hängen. Die alte
-- Nachsignierungs-Warteschlange (mehrere offene Belege in einem "Lauf" nachholen)
-- hat damit keine Aufgabe mehr.
--
-- Stattdessen: bfr_ausfaelle/-ereignisse protokollieren jede Störung (Dienst nicht
-- erreichbar oder "Sicherheitseinrichtung ausgefallen") episodenweise, damit wir
-- ab 24h eine Warnung zeigen können, bevor mit 48h die FON-Meldepflicht greift.
--
-- Geprüft vor dieser Migration: nur 26 Test-Belege (27.6.-4.7., aus den Mock-BFR-
-- Testläufen) mit bfr_status='ausstehend', 0 Zeilen in bfr_nachsignierungs_laeufe
-- und bfr_nullbelege - keine echten Daten betroffen.

-- Alte Nachsignierungs-Warteschlange entfernen
ALTER TABLE kassen_bons DROP FOREIGN KEY fk_bon_bfrlauf;
ALTER TABLE kassen_bons
    DROP COLUMN nachsignierungs_lauf_id,
    DROP COLUMN bfr_status,
    DROP COLUMN bfr_fehlergrund;

ALTER TABLE bfr_nullbelege
    DROP COLUMN bfr_status,
    DROP COLUMN bfr_fehlergrund;

DROP TABLE bfr_nachsignierungs_laeufe;

-- Neu: Ausfall-Episoden (eine Zeile pro durchgehender Störung, geloest_am NULL = aktiv)
CREATE TABLE bfr_ausfaelle (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kasse_id            INT UNSIGNED NOT NULL,
    erste_erkennung_am  DATETIME NOT NULL,
    letzte_erkennung_am DATETIME NOT NULL,
    geloest_am          DATETIME NULL,
    anzahl_ereignisse   INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_bfrausfall_kasse (kasse_id),
    CONSTRAINT fk_bfrausfall_kasse FOREIGN KEY (kasse_id) REFERENCES kassen (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Neu: Einzelne Vorfälle innerhalb einer Episode (welcher Bon, wann, wie oft versucht)
CREATE TABLE bfr_ausfall_ereignisse (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ausfall_id      INT UNSIGNED NOT NULL,
    typ             ENUM('dienst_nicht_erreichbar','sicherheitseinrichtung_ausgefallen') NOT NULL,
    bon_nr          VARCHAR(50) NULL,
    anzahl_versuche INT UNSIGNED NOT NULL DEFAULT 1,
    aufgetreten_am  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bfrausfallereignis_ausfall (ausfall_id),
    CONSTRAINT fk_bfrausfallereignis_ausfall FOREIGN KEY (ausfall_id) REFERENCES bfr_ausfaelle (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
