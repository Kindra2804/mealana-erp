-- Migration 062: Rechnungen, Auftragsdokumente, Statuslog
-- rechnungen: eigene fortlaufende Nummer via dokument_nummern (typ='rechnung')
-- storno_von: Gutschrift referenziert die stornierte Rechnung
-- auftrag_dokumente: Dateipfade generierter PDFs pro Auftrag
-- auftrag_statuslog: jede Statusänderung mit Diff + Benutzer

CREATE TABLE rechnungen (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    rechnung_nr  VARCHAR(20)   NOT NULL UNIQUE,   -- R-2026-00001
    auftrag_id   INT UNSIGNED  NOT NULL,
    nettobetrag  DECIMAL(10,2) NOT NULL,
    steuerbetrag DECIMAL(10,2) NOT NULL,
    bruttobetrag DECIMAL(10,2) NOT NULL,
    faellig_am   DATE          NULL,
    storniert    TINYINT(1)    NOT NULL DEFAULT 0,
    storno_von   INT UNSIGNED  NULL,              -- bei Gutschrift: referenzierte Rechnung
    erstellt_am  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von INT UNSIGNED  NOT NULL,

    CONSTRAINT fk_re_auftrag  FOREIGN KEY (auftrag_id)  REFERENCES auftraege (id)  ON UPDATE CASCADE,
    CONSTRAINT fk_re_storno   FOREIGN KEY (storno_von)  REFERENCES rechnungen (id) ON UPDATE CASCADE,
    CONSTRAINT fk_re_benutzer FOREIGN KEY (erstellt_von) REFERENCES benutzer (id)  ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auftrag_dokumente (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    auftrag_id   INT UNSIGNED  NOT NULL,
    typ          ENUM('auftragsbestaetigung','lieferschein','rechnung','gutschrift','mahnung') NOT NULL,
    dateiname    VARCHAR(255)  NOT NULL,
    erstellt_am  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von INT UNSIGNED  NOT NULL,

    CONSTRAINT fk_adok_auftrag  FOREIGN KEY (auftrag_id)  REFERENCES auftraege (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_adok_benutzer FOREIGN KEY (erstellt_von) REFERENCES benutzer (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auftrag_statuslog (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    auftrag_id       INT UNSIGNED NOT NULL,
    felder_geaendert TEXT         NULL,   -- JSON: {"zahlungsstatus":["ausstehend","bezahlt"]}
    notiz            TEXT         NULL,
    erstellt_am      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von     INT UNSIGNED NOT NULL,

    CONSTRAINT fk_alog_auftrag  FOREIGN KEY (auftrag_id)  REFERENCES auftraege (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_alog_benutzer FOREIGN KEY (erstellt_von) REFERENCES benutzer (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
