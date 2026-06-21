-- Migration 053: Mietfächer-Redesign
--
-- Vorher: mietfaecher gehörte zum Partner (partner_id FK, Mietzeitraum direkt drauf)
-- Nachher: mietfaecher = physische Einheit (Regal, Maße, Standardpreis)
--          mietfach_mietvertraege = wer mietet wann (History-fähig)
--
-- Da noch kein Produktivbetrieb: destructive migration OK.

-- ---------------------------------------------------------------------------
-- 1. Alte FK-Constraint + Spalten entfernen
-- ---------------------------------------------------------------------------

ALTER TABLE mietfaecher
    DROP FOREIGN KEY fk_mietf_partner,
    DROP INDEX       idx_mietf_partner,
    DROP COLUMN      partner_id,
    DROP COLUMN      mietbetrag_monatlich,
    DROP COLUMN      mwst_satz,
    DROP COLUMN      mietbeginn,
    DROP COLUMN      mietende;

-- ---------------------------------------------------------------------------
-- 2. Physische Eigenschaften ergänzen
-- ---------------------------------------------------------------------------

ALTER TABLE mietfaecher
    ADD COLUMN ort_beschreibung VARCHAR(200) NULL  AFTER fach_bezeichnung,
    ADD COLUMN laenge_cm        DECIMAL(6,1) NULL  AFTER ort_beschreibung,
    ADD COLUMN breite_cm        DECIMAL(6,1) NULL  AFTER laenge_cm,
    ADD COLUMN hoehe_cm         DECIMAL(6,1) NULL  AFTER breite_cm,
    ADD COLUMN standard_preis   DECIMAL(10,2) NULL AFTER hoehe_cm,
    ADD COLUMN notiz            TEXT         NULL  AFTER standard_preis;

-- ---------------------------------------------------------------------------
-- 3. Mietverträge (wer mietet welches Fach, wann, zu welchem Preis)
-- ---------------------------------------------------------------------------

CREATE TABLE mietfach_mietvertraege (
    id                      INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    mietfach_id             INT UNSIGNED    NOT NULL,
    partner_id              INT UNSIGNED    NOT NULL,
    mietbetrag_monatlich    DECIMAL(10,2)   NOT NULL,
    mwst_satz               DECIMAL(5,2)    NOT NULL DEFAULT 20.00,
    mietbeginn              DATE            NOT NULL,
    mietende                DATE            NULL,           -- NULL = unbefristet / läuft noch
    notiz                   TEXT            NULL,
    erstellt_am             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_mv_fach    FOREIGN KEY (mietfach_id) REFERENCES mietfaecher(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mv_partner FOREIGN KEY (partner_id)  REFERENCES partner(id)     ON DELETE RESTRICT,
    INDEX idx_mv_fach    (mietfach_id),
    INDEX idx_mv_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
