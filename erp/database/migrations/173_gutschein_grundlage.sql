-- Gutschein-Modul, Baustufe 1: Datengrundlage.
-- Planung siehe project_gutscheine.md (Memory) -- ERP ist Source of Truth,
-- WooCommerce bekommt nur den nackten Code als gespiegelten fixed_cart-Coupon.

-- Reine Design-Vorlagen (Hintergrundbild fuers PDF/Vorschau), KEIN Betrag mehr
-- daran -- der Betrag ist frei waehlbar (siehe gutscheine.betrag).
CREATE TABLE gutschein_vorlagen (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                VARCHAR(100) NOT NULL,
    hintergrundbild_pfad VARCHAR(255) NULL,
    aktiv               TINYINT(1) NOT NULL DEFAULT 1,
    sort_order          INT UNSIGNED NOT NULL DEFAULT 0,
    erstellt_am         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE gutscheine (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code                VARCHAR(30) NOT NULL,
    vorlage_id          INT UNSIGNED NULL,
    betrag              DECIMAL(10,2) NOT NULL,
    restguthaben        DECIMAL(10,2) NOT NULL,
    gueltig_bis         DATE NULL,
    status              ENUM('aktiv','teilweise','eingeloest','abgelaufen','storniert') NOT NULL DEFAULT 'aktiv',
    kunden_id           INT UNSIGNED NULL,
    empfaenger_name     VARCHAR(150) NULL,
    empfaenger_email    VARCHAR(150) NULL,
    zustellung_am       DATE NULL,
    versandart          ENUM('versenden','selbst_ausdrucken') NOT NULL DEFAULT 'selbst_ausdrucken',
    grusstext           TEXT NULL,
    versendet_am        DATETIME NULL,
    woo_coupon_id       INT UNSIGNED NULL,
    shop_id             INT UNSIGNED NULL,
    kanal_erstellt      ENUM('kasse','erp','woocommerce','manuell') NOT NULL DEFAULT 'manuell',
    auftrag_id_ursprung INT UNSIGNED NULL COMMENT 'Bestellung/Bon, aus dem der Gutschein entstand (Kauf ODER Erstattung)',
    ausgestellt_von     INT UNSIGNED NOT NULL,
    erstellt_am         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aktualisiert_am     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gutschein_code (code),
    KEY idx_gutschein_kunde (kunden_id),
    KEY idx_gutschein_status (status),
    CONSTRAINT fk_gutschein_vorlage FOREIGN KEY (vorlage_id) REFERENCES gutschein_vorlagen (id),
    CONSTRAINT fk_gutschein_kunde FOREIGN KEY (kunden_id) REFERENCES kunden (id),
    CONSTRAINT fk_gutschein_shop FOREIGN KEY (shop_id) REFERENCES shops (id),
    CONSTRAINT fk_gutschein_ausgestellt_von FOREIGN KEY (ausgestellt_von) REFERENCES benutzer (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE gutschein_transaktionen (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    gutschein_id INT UNSIGNED NOT NULL,
    auftrag_id   INT UNSIGNED NULL,
    kassen_bon_id INT UNSIGNED NULL,
    betrag       DECIMAL(10,2) NOT NULL COMMENT 'negativ = Einloesung, positiv = Aufladung/Erstattung',
    kanal        ENUM('kasse','erp','woocommerce') NOT NULL,
    notiz        VARCHAR(255) NULL,
    benutzer_id  INT UNSIGNED NULL,
    erstellt_am  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_gtrans_gutschein (gutschein_id),
    CONSTRAINT fk_gtrans_gutschein FOREIGN KEY (gutschein_id) REFERENCES gutscheine (id),
    CONSTRAINT fk_gtrans_auftrag FOREIGN KEY (auftrag_id) REFERENCES auftraege (id),
    CONSTRAINT fk_gtrans_benutzer FOREIGN KEY (benutzer_id) REFERENCES benutzer (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- auftraege.gutschein_id existiert schon seit Migration 060ff als vorsorgliche Spalte
-- ohne FK (Ziel-Tabelle gab es noch nicht) -- jetzt echte Referenz nachziehen.
ALTER TABLE auftraege
    ADD CONSTRAINT fk_auftraege_gutschein FOREIGN KEY (gutschein_id) REFERENCES gutscheine (id);

-- kassen_bons hatte bisher nur gutschein_code (Freitext, keine echte Validierung/
-- Restguthaben-Pruefung) -- echte FK-Spalte ergaenzen, alte Spalten bleiben fuer
-- historische Bons unangetastet stehen.
ALTER TABLE kassen_bons
    ADD COLUMN gutschein_id INT UNSIGNED NULL AFTER gutschein_code,
    ADD CONSTRAINT fk_kassenbons_gutschein FOREIGN KEY (gutschein_id) REFERENCES gutscheine (id);

-- Markiert den (oder die, je Shop) Artikel, der im Shop als "Gutschein kaufen"-
-- Produkt dient -- ShopBestellungSyncService routet Bestellpositionen mit dieser
-- Artikel-Kennung in die Gutschein-Erzeugung statt der normalen Positions-Logik.
ALTER TABLE artikel
    ADD COLUMN ist_gutschein TINYINT(1) NOT NULL DEFAULT 0 AFTER keine_lagerbestandsfuehrung;

-- Gueltigkeitsdauer als System-Einstellung statt hartcodiert (siehe project_gutscheine.md,
-- Rechtsfrage 2026-08-29 -- kein exaktes gesetzliches Minimum, 10 Jahre ist Praxis-Standard).
INSERT INTO system_einstellungen (schluessel, wert) VALUES
    ('gutschein_gueltigkeit_tage', '3650'),
    ('gutschein_mindestbetrag_shop', '10.00');
