-- Partner-Modul: Belegwesen
--
-- dokument_nummern: Zentrale fortlaufende Nummernkreise (AT-Pflicht: lückenlos, je Typ+Jahr).
--   Vergabe via SELECT ... FOR UPDATE + UPDATE in einer Transaktion (kein Race-Condition-Risiko).
--
-- miet_rechnungen:      MeaLana → Mieter  (Mietbetrag, monatlich halbautomatisch)
-- kommissions_abrechnungen: MeaLana → Partner (Verkaufserlöse abrechnen)
--   Gegenverrechnung: System berechnet Saldo = Erlöse - Miete → ein Dokument
--   Getrennt:         Mietrechnung + Abrechnung separat

-- ---------------------------------------------------------------------------
-- Dokumentennummern (alle Belegkreise zentral)
-- ---------------------------------------------------------------------------

CREATE TABLE dokument_nummern (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    typ         ENUM('rechnung','gutschrift','lieferschein','mietrechnung','abrechnung') NOT NULL,
    praefix     VARCHAR(10)     NOT NULL,
    jahr        SMALLINT        NOT NULL,
    letzt_nr    INT UNSIGNED    NOT NULL DEFAULT 0,

    UNIQUE KEY uk_dok_typ_jahr (typ, jahr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Startwerte für aktuelles Jahr
INSERT INTO dokument_nummern (typ, praefix, jahr, letzt_nr) VALUES
    ('rechnung',     'R',  YEAR(CURDATE()), 0),
    ('gutschrift',   'GS', YEAR(CURDATE()), 0),
    ('lieferschein', 'LS', YEAR(CURDATE()), 0),
    ('mietrechnung', 'MR', YEAR(CURDATE()), 0),
    ('abrechnung',   'AB', YEAR(CURDATE()), 0);

-- ---------------------------------------------------------------------------
-- Miet-Rechnungen (MeaLana stellt Rechnung an Mieter für Mietbetrag)
-- ---------------------------------------------------------------------------

CREATE TABLE miet_rechnungen (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    fach_id         INT UNSIGNED    NOT NULL,
    partner_id      INT UNSIGNED    NOT NULL,
    periode         CHAR(7)         NOT NULL,           -- Format: 'YYYY-MM'
    betrag_netto    DECIMAL(10,2)   NOT NULL,
    mwst_satz       DECIMAL(5,2)    NOT NULL,
    betrag_brutto   DECIMAL(10,2)   NOT NULL,
    rechnungs_nr    VARCHAR(20)     NOT NULL,
    erstellt_am     DATE            NOT NULL,
    faellig_am      DATE            NOT NULL,
    bezahlt_am      DATE            NULL,
    status          ENUM('offen','bezahlt','storniert') NOT NULL DEFAULT 'offen',
    beleg_pfad      VARCHAR(255)    NULL,               -- gespeichertes PDF

    CONSTRAINT fk_mietrg_fach    FOREIGN KEY (fach_id)    REFERENCES mietfaecher(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mietrg_partner FOREIGN KEY (partner_id) REFERENCES partner(id)     ON DELETE RESTRICT,
    UNIQUE KEY  uk_mietrg_nr     (rechnungs_nr),
    INDEX idx_mietrg_periode     (periode),
    INDEX idx_mietrg_partner     (partner_id),
    INDEX idx_mietrg_status      (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Kommissions-Abrechnungen (MeaLana zahlt Verkaufserlöse an Partner aus)
-- ---------------------------------------------------------------------------

CREATE TABLE kommissions_abrechnungen (
    id                      INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    partner_id              INT UNSIGNED    NOT NULL,
    periode_von             DATE            NOT NULL,
    periode_bis             DATE            NOT NULL,
    gesamt_verkauft         DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    provisions_satz         DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    provision_betrag        DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    auszahlung_betrag       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    abrechnungs_typ         ENUM('gutschrift','fremdrechnung','info') NOT NULL,
    belegnummer             VARCHAR(20)     NULL,
    fremdrechnungs_nr       VARCHAR(50)     NULL,       -- wenn Partner selbst Rechnung stellt
    fremdrechnungs_datum    DATE            NULL,
    beleg_pfad              VARCHAR(255)    NULL,
    erstellt_am             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ausgezahlt_am           DATE            NULL,
    status                  ENUM('erstellt','ausgezahlt','storniert') NOT NULL DEFAULT 'erstellt',

    CONSTRAINT fk_komabrg_partner FOREIGN KEY (partner_id) REFERENCES partner(id) ON DELETE RESTRICT,
    INDEX idx_komabrg_partner     (partner_id),
    INDEX idx_komabrg_periode     (periode_von, periode_bis),
    INDEX idx_komabrg_status      (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
