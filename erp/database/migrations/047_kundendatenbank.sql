-- Kundendatenbank
--
-- Verschlüsselung: alle _enc Felder werden PHP-seitig mit AES-256-GCM verschlüsselt.
-- Format: [16 Byte IV] + [Ciphertext], gespeichert als BLOB.
-- Key liegt in .env als ENCRYPTION_MASTER_KEY (256-bit hex) -- NICHT in der DB!
-- email_hash = HMAC-SHA256(strtolower(email), ENCRYPTION_SEARCH_KEY) für Suche per WHERE.
-- Suche auf Name/Adresse: in PHP entschlüsseln + dort filtern (bei <50k Kunden OK).
--
-- DSGVO Löschung (Art. 17): Kunden-Key ungültig machen (Crypto-Shredding).
-- Transaktionsbelege bleiben erhalten (7-Jahre Aufbewahrungspflicht), aber nicht mehr zuordenbar.

-- ---------------------------------------------------------------------------
-- Kern-Kundentabelle
-- ---------------------------------------------------------------------------

CREATE TABLE kunden (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kundennummer            VARCHAR(20)     NOT NULL,
    status                  ENUM('aktiv','gesperrt','geloescht') NOT NULL DEFAULT 'aktiv',
    ist_laufkunde           TINYINT(1)      NOT NULL DEFAULT 0,
    ist_firma               TINYINT(1)      NOT NULL DEFAULT 0,
    kundengruppe_id         INT UNSIGNED    NULL,
    zahlungsbedingung_id    INT UNSIGNED    NULL,
    standardzahlungsart     ENUM('vorkasse','rechnung','kreditkarte','paypal','bar') NULL,
    kreditlimit             DECIMAL(10,2)   NULL,
    sprache                 VARCHAR(5)      NOT NULL DEFAULT 'de',
    kundenherkunft          ENUM('shop','messe','empfehlung','walkin','kasse','erp') NOT NULL DEFAULT 'erp',
    -- Verschlüsselt
    vorname_enc             BLOB            NULL,
    nachname_enc            BLOB            NULL,
    firmenname_enc          BLOB            NULL,
    email_enc               BLOB            NULL,
    email_hash              CHAR(64)        NULL,
    telefon_enc             BLOB            NULL,
    mobil_enc               BLOB            NULL,
    geburtsdatum_enc        BLOB            NULL,
    uid_nummer_enc          BLOB            NULL,
    notiz_enc               BLOB            NULL,
    -- Timestamps
    erstellt_am             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aktualisiert_am         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_kunde_kg    FOREIGN KEY (kundengruppe_id)      REFERENCES kundengruppen(id)      ON DELETE SET NULL,
    CONSTRAINT fk_kunde_zb    FOREIGN KEY (zahlungsbedingung_id) REFERENCES zahlungsbedingungen(id) ON DELETE SET NULL,
    UNIQUE KEY uq_kundennummer (kundennummer),
    KEY idx_email_hash (email_hash),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Laufkunde: fixer Systemdatensatz, wird an der Kasse als Anonymous-Kunde verwendet
INSERT INTO kunden (kundennummer, status, ist_laufkunde, kundenherkunft)
VALUES ('LAUFKUNDE', 'aktiv', 1, 'kasse');

-- ---------------------------------------------------------------------------
-- Adressen (Haupt / Rechnung / Lieferung -- mehrere pro Kunde möglich)
-- ---------------------------------------------------------------------------

CREATE TABLE kunden_adressen (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kunde_id        INT UNSIGNED    NOT NULL,
    adresstyp       ENUM('haupt','rechnung','lieferung') NOT NULL DEFAULT 'haupt',
    ist_standard    TINYINT(1)      NOT NULL DEFAULT 0,
    land            VARCHAR(2)      NOT NULL DEFAULT 'AT',
    -- Verschlüsselt
    firma_enc       BLOB            NULL,
    vorname_enc     BLOB            NULL,
    nachname_enc    BLOB            NULL,
    strasse_enc     BLOB            NULL,
    hausnummer_enc  BLOB            NULL,
    plz_enc         BLOB            NULL,
    ort_enc         BLOB            NULL,
    zusatz_enc      BLOB            NULL,
    erstellt_am     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_kaddr_kunde FOREIGN KEY (kunde_id) REFERENCES kunden(id) ON DELETE CASCADE,
    KEY idx_kaddr_kunde (kunde_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Ansprechpartner (B2B: mehrere Kontakte pro Firmenkunde)
-- ---------------------------------------------------------------------------

CREATE TABLE kunden_ansprechpartner (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kunde_id        INT UNSIGNED    NOT NULL,
    ist_haupt       TINYINT(1)      NOT NULL DEFAULT 0,
    -- Verschlüsselt
    vorname_enc     BLOB            NULL,
    nachname_enc    BLOB            NULL,
    position_enc    BLOB            NULL,
    email_enc       BLOB            NULL,
    email_hash      CHAR(64)        NULL,
    telefon_enc     BLOB            NULL,
    notiz_enc       BLOB            NULL,
    erstellt_am     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_kansp_kunde FOREIGN KEY (kunde_id) REFERENCES kunden(id) ON DELETE CASCADE,
    KEY idx_kansp_kunde (kunde_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- DSGVO Consent-Log (unveränderlich -- jede Einwilligung/Widerruf als eigene Zeile)
-- ---------------------------------------------------------------------------

CREATE TABLE kunden_dsgvo_consent (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kunde_id        INT UNSIGNED    NOT NULL,
    consent_typ     ENUM('newsletter','marketing','profiling') NOT NULL,
    eingewilligt    TINYINT(1)      NOT NULL,
    eingewilligt_am TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    quelle          ENUM('shop','messe','kasse','erp_manuell','telefon') NOT NULL,
    ip_adresse      VARCHAR(45)     NULL,
    widerrufen_am   TIMESTAMP       NULL,
    kommentar       VARCHAR(255)    NULL,
    CONSTRAINT fk_consent_kunde FOREIGN KEY (kunde_id) REFERENCES kunden(id) ON DELETE CASCADE,
    KEY idx_consent_kunde (kunde_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Shop-Verknüpfungen (WooCommerce + eigener Shop)
-- shop_id referenziert shops-Tabelle (kommt mit Shop-Modul -- kein FK gesetzt)
-- ---------------------------------------------------------------------------

CREATE TABLE kunden_shops (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kunde_id        INT UNSIGNED    NOT NULL,
    shop_id         INT UNSIGNED    NOT NULL,
    external_id     VARCHAR(255)    NULL,
    sync_status     ENUM('pending','synced','error') NOT NULL DEFAULT 'pending',
    synced_at       TIMESTAMP       NULL,
    fehler_meldung  TEXT            NULL,
    CONSTRAINT fk_kdshop_kunde FOREIGN KEY (kunde_id) REFERENCES kunden(id) ON DELETE CASCADE,
    UNIQUE KEY uq_kunde_shop (kunde_id, shop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Merge-Queue: erkannte Duplikate zur manuellen Zusammenführung
-- ---------------------------------------------------------------------------

CREATE TABLE kunden_merge_queue (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kunde_a_id          INT UNSIGNED    NOT NULL,
    kunde_b_id          INT UNSIGNED    NOT NULL,
    erkannt_am          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erkennungsgrund     VARCHAR(255)    NOT NULL,
    status              ENUM('offen','gemerged','abgelehnt') NOT NULL DEFAULT 'offen',
    bearbeitet_von      INT UNSIGNED    NULL,
    bearbeitet_am       TIMESTAMP       NULL,
    CONSTRAINT fk_merge_a FOREIGN KEY (kunde_a_id) REFERENCES kunden(id) ON DELETE CASCADE,
    CONSTRAINT fk_merge_b FOREIGN KEY (kunde_b_id) REFERENCES kunden(id) ON DELETE CASCADE,
    KEY idx_merge_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
