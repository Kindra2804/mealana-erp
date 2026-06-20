-- Partner-Modul: Stammdaten
--
-- Externe Partner in zwei Richtungen:
--   kommission  → Mietfach-Inhaber (ihre Ware bei uns, wir verkaufen)
--   spende      → Organisationen wie Yarnpride (Artikel gegen Spende, Geld geht weiter)
--   beides      → beides gleichzeitig möglich
--
-- Mietfächer: ein Partner kann mehrere Fächer haben.
-- Mietbetrag + MwSt pro Fach konfigurierbar (MwSt = 0 wenn MeaLana im KU-Modus).

-- ---------------------------------------------------------------------------
-- Kern-Partnertabelle
-- ---------------------------------------------------------------------------

CREATE TABLE partner (
    id                      INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(200)    NOT NULL,
    typ                     ENUM('kommission','spende','beides') NOT NULL DEFAULT 'kommission',
    email                   VARCHAR(200)    NULL,
    telefon                 VARCHAR(50)     NULL,
    iban                    VARCHAR(34)     NULL,
    uid_nummer              VARCHAR(30)     NULL,
    zvr_nummer              VARCHAR(30)     NULL,        -- Vereinsregister (z.B. Yarnpride)
    kleinunternehmer        TINYINT(1)      NOT NULL DEFAULT 0,
    provisions_satz         DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    -- Abrechnung Kommissionserlöse
    abrechnungs_modus       ENUM('getrennt','gegenverrechnung') NOT NULL DEFAULT 'getrennt',
    abrechnungs_beleg_typ   ENUM('gutschrift','fremdrechnung','info') NOT NULL DEFAULT 'gutschrift',
    notiz                   TEXT            NULL,
    aktiv                   TINYINT(1)      NOT NULL DEFAULT 1,
    erstellt_am             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    geaendert_am            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_partner_typ   (typ),
    INDEX idx_partner_aktiv (aktiv)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Mietfächer (n pro Partner möglich, max. ~10 in der Praxis)
-- ---------------------------------------------------------------------------

CREATE TABLE mietfaecher (
    id                      INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    partner_id              INT UNSIGNED    NOT NULL,
    fach_bezeichnung        VARCHAR(50)     NOT NULL,       -- z.B. "Fach 3", "Regal B"
    mietbetrag_monatlich    DECIMAL(10,2)   NOT NULL,
    mwst_satz               DECIMAL(5,2)    NOT NULL DEFAULT 20.00,
    mietbeginn              DATE            NOT NULL,
    mietende                DATE            NULL,           -- NULL = unbefristet
    aktiv                   TINYINT(1)      NOT NULL DEFAULT 1,
    erstellt_am             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_mietf_partner FOREIGN KEY (partner_id) REFERENCES partner(id) ON DELETE RESTRICT,
    INDEX idx_mietf_partner     (partner_id),
    INDEX idx_mietf_aktiv       (aktiv)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
