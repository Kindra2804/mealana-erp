-- Migration 060: Auftragsmodul — Kerntabelle auftraege
-- Zahlungsstatus + Lieferstatus getrennt (JTL-Ansatz)
-- auftrag_nr wird via AuftragService::auftragNr() generiert (A-2026-00001)
-- Nummernkreis 'auftrag' in dokument_nummern ergänzt

ALTER TABLE dokument_nummern
    MODIFY COLUMN typ ENUM(
        'rechnung','gutschrift','lieferschein','mietrechnung','abrechnung','auftrag'
    ) NOT NULL;

INSERT INTO dokument_nummern (typ, praefix, jahr, letzt_nr)
VALUES ('auftrag', 'A', YEAR(CURDATE()), 0);

CREATE TABLE auftraege (
    id                        INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    auftrag_nr                VARCHAR(20)   NOT NULL UNIQUE,

    kunden_id                 INT UNSIGNED  NULL,
    kunden_snapshot           TEXT          NULL,   -- JSON: name, strasse, plz, ort, land, email
    lieferadresse_snapshot    TEXT          NULL,
    rechnungsadresse_snapshot TEXT          NULL,

    kanal                     ENUM('woocommerce','manuell','kasse') NOT NULL DEFAULT 'manuell',
    kanal_auftrag_id          INT UNSIGNED  NULL,   -- WooCommerce order_id

    zahlungsstatus            ENUM('ausstehend','bezahlt','teilbezahlt','erstattet','storniert')
                                            NOT NULL DEFAULT 'ausstehend',
    lieferstatus              ENUM('neu','in_bearbeitung','versandbereit','teilgeliefert',
                                   'zurueckgestellt','versendet','abgeschlossen','storniert')
                                            NOT NULL DEFAULT 'neu',

    zahlungsart               ENUM('vorkasse','paypal','rechnung','bar','gutschein','gemischt')
                                            NOT NULL DEFAULT 'vorkasse',
    zahlungsbedingung_id      INT UNSIGNED  NULL,

    gutschein_id              INT UNSIGNED  NULL,
    gutschein_betrag          DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    versandkosten             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    rabatt_gesamt             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    nettobetrag               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    steuerbetrag              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    bruttobetrag              DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    bezahlt_am                DATETIME      NULL,
    mahnung_stufe             TINYINT UNSIGNED NOT NULL DEFAULT 0,
    mahnung_gesendet_am       DATETIME      NULL,

    tracking_nr               VARCHAR(100)  NULL,
    versanddienstleister      VARCHAR(50)   NULL,

    notiz_intern              TEXT          NULL,
    notiz_versand             TEXT          NULL,

    erstellt_am               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aktualisiert_am           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    erstellt_von              INT UNSIGNED  NOT NULL,

    CONSTRAINT fk_auftrag_kunde    FOREIGN KEY (kunden_id)            REFERENCES kunden (id)             ON UPDATE CASCADE,
    CONSTRAINT fk_auftrag_zahlung  FOREIGN KEY (zahlungsbedingung_id) REFERENCES zahlungsbedingungen (id) ON UPDATE CASCADE,
    CONSTRAINT fk_auftrag_benutzer FOREIGN KEY (erstellt_von)         REFERENCES benutzer (id)           ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
