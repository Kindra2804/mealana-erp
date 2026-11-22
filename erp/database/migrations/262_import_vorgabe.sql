-- 262: Vorgaben je Vater/Einzelartikel beim Import (Babsi/Jacky 2026-11-22)
--
-- In der Vorschau "JTL: neue Nummern" kann Babsi je Vater oder Einzelartikel
--   - die neue Nummer genau vorgeben (z.B. JTL "7004KAZ" -> "SO-KAZ-7004"); die Kinder richten sich danach
--     (SO-KAZ-7004-0001 …). Die Nummer wird so genommen, wie sie dasteht – das Kürzel wird NICHT nochmals davorgesetzt.
--   - den Hersteller zuordnen, wenn er in JTL fehlt oder falsch ist (ERP-Hersteller, bestimmt auch das Kürzel).
-- Leere Felder = nichts vorgegeben, dann gilt das Schema (bzw. import_umbenennung, Migr. 260).
-- nummer = Nummer wie in der Quelle (ohne "#"). Wiederholbar.

CREATE TABLE IF NOT EXISTS import_vorgabe (
    quelle        VARCHAR(30)  NOT NULL,
    nummer        VARCHAR(50)  NOT NULL,
    nummer_neu    VARCHAR(50)  NULL,
    hersteller_id INT UNSIGNED NULL,
    benutzer_id   INT UNSIGNED NULL,
    geaendert_am  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (quelle, nummer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
