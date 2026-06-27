-- Migration 077: Kassensystem (Phase 1)
-- Tabellen: kassen, kassen_bons, kassen_bon_positionen, kassenbuch, offene_auswahl

CREATE TABLE kassen (
    id           INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100)     NOT NULL,
    kasse_nr     VARCHAR(10)      NOT NULL UNIQUE,
    lager_id     INT(10) UNSIGNED NOT NULL,
    aktiv        TINYINT(1)       NOT NULL DEFAULT 1,
    erstellt_am  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lager_id) REFERENCES lager(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO kassen (name, kasse_nr, lager_id)
SELECT 'Hauptkasse', 'K1', MIN(id) FROM lager;

CREATE TABLE kassen_bons (
    id               INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bon_nr           VARCHAR(30)      NOT NULL UNIQUE,
    typ              ENUM('verkauf','storno','x_bon','z_bon') NOT NULL DEFAULT 'verkauf',
    kasse_id         INT(10) UNSIGNED NOT NULL DEFAULT 1,
    auftrag_id       INT(10) UNSIGNED NULL,
    kunden_id        INT(10) UNSIGNED NULL,
    zahlungsart      ENUM('bar','karte_extern','gutschein','kombi') NOT NULL DEFAULT 'bar',
    bruttobetrag     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    gegeben          DECIMAL(10,2) NULL,
    rueckgeld        DECIMAL(10,2) NULL,
    bar_betrag       DECIMAL(10,2) NULL,
    karten_betrag    DECIMAL(10,2) NULL,
    gutschein_code   VARCHAR(100) NULL,
    gutschein_betrag DECIMAL(10,2) NULL,
    rksv_signatur    TEXT         NULL,
    rksv_qr          VARCHAR(500) NULL,
    benutzer_id      INT(10) UNSIGNED NOT NULL,
    erstellt_am      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    storniert        TINYINT(1)   NOT NULL DEFAULT 0,
    storno_von_id    INT(10) UNSIGNED NULL,
    gedruckt         TINYINT(1)   NOT NULL DEFAULT 0,
    notiz            VARCHAR(500) NULL,
    FOREIGN KEY (kasse_id)    REFERENCES kassen(id),
    FOREIGN KEY (auftrag_id)  REFERENCES auftraege(id),
    FOREIGN KEY (kunden_id)   REFERENCES kunden(id),
    FOREIGN KEY (benutzer_id) REFERENCES benutzer(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE kassen_bon_positionen (
    id                 INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bon_id             INT(10) UNSIGNED NOT NULL,
    artikel_id         INT(10) UNSIGNED NULL,
    bezeichnung        VARCHAR(300) NOT NULL,
    ean                VARCHAR(50)  NULL,
    menge              DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    einzelpreis_brutto DECIMAL(10,4) NOT NULL,
    rabatt_prozent     DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    steuer_prozent     DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    charge             VARCHAR(100) NULL,
    sort_order         INT          NOT NULL DEFAULT 0,
    FOREIGN KEY (bon_id) REFERENCES kassen_bons(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE kassenbuch (
    id          INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    typ         ENUM('einlage','entnahme','anfangsbestand') NOT NULL,
    betrag      DECIMAL(10,2)    NOT NULL,
    notiz       VARCHAR(500)     NULL,
    kasse_id    INT(10) UNSIGNED NOT NULL DEFAULT 1,
    bon_id      INT(10) UNSIGNED NULL,
    benutzer_id INT(10) UNSIGNED NOT NULL,
    erstellt_am DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kasse_id)    REFERENCES kassen(id),
    FOREIGN KEY (bon_id)      REFERENCES kassen_bons(id),
    FOREIGN KEY (benutzer_id) REFERENCES benutzer(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Offene Auswahl: Artikel mitgeben (Ansicht/Farbvergleich), kein Verkauf yet
CREATE TABLE offene_auswahl (
    id            INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kunden_name   VARCHAR(200)     NULL,
    kunden_id     INT(10) UNSIGNED NULL,
    lager_id      INT(10) UNSIGNED NOT NULL DEFAULT 1,
    positionen    JSON             NOT NULL,
    ausgegeben_am DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    rueckgabe_bis DATE             NULL,
    status        ENUM('offen','gekauft','zurueck') NOT NULL DEFAULT 'offen',
    bon_id        INT(10) UNSIGNED NULL,
    notiz         VARCHAR(500)     NULL,
    benutzer_id   INT(10) UNSIGNED NOT NULL,
    FOREIGN KEY (kunden_id)   REFERENCES kunden(id),
    FOREIGN KEY (bon_id)      REFERENCES kassen_bons(id),
    FOREIGN KEY (benutzer_id) REFERENCES benutzer(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
