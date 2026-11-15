-- 249: Newsletter Schritt 1 — Empfänger + Einwilligungsnachweis (Jacky 2026-11-15)
-- Ein Newsletter je Shop möglich; Empfänger je Shop (gleiche Adresse kann in mehreren Shops stehen).
-- E-Mail und Name verschlüsselt wie bei den Kunden (Encryption::encrypt, Suche/Dubletten über email_hash).
-- Einwilligungsnachweis (DSGVO, TKG § 174): Anmelde-/Opt-in-Zeitpunkt + IP am Empfänger, jede Änderung
-- unveränderlich in newsletter_ereignisse. Abgemeldete bleiben als Zeile stehen (status 'abgemeldet'),
-- damit sie bei einem Import nie wieder aktiv werden.
-- Wiederholbar.

CREATE TABLE IF NOT EXISTS newsletter_empfaenger (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shop_id               INT UNSIGNED NOT NULL,
    email_enc             BLOB         NOT NULL,
    email_hash            CHAR(64)     NOT NULL,
    anrede                VARCHAR(30)  NULL,
    vorname_enc           BLOB         NULL,
    nachname_enc          BLOB         NULL,
    kunden_id             INT UNSIGNED NULL,                 -- passender ERP-Kunde (über email_hash), falls vorhanden
    status                ENUM('ausstehend','aktiv','abgemeldet') NOT NULL DEFAULT 'ausstehend',
    quelle                VARCHAR(30)  NOT NULL,             -- jtl_import, shop_formular, erp
    eingetragen_am        DATETIME     NOT NULL,
    anmelde_ip            VARCHAR(45)  NULL,
    optin_am              DATETIME     NULL,                 -- Bestätigung (Double-Opt-in)
    optin_ip              VARCHAR(45)  NULL,
    abgemeldet_am         DATETIME     NULL,
    letzter_newsletter_am DATETIME     NULL,
    token                 CHAR(32)     NOT NULL,             -- für Bestätigungs-/Abmeldelinks (beleg.mealana.at)
    erstellt_am           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    geaendert_am          TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_nle_shop_email (shop_id, email_hash),
    UNIQUE KEY uq_nle_token (token),
    KEY idx_nle_status (shop_id, status),
    KEY idx_nle_kunde (kunden_id),
    CONSTRAINT fk_nle_shop FOREIGN KEY (shop_id) REFERENCES shops (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Unveränderliches Protokoll (Nachweis): importiert, angemeldet, bestaetigt, abgemeldet, …
CREATE TABLE IF NOT EXISTS newsletter_ereignisse (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empfaenger_id INT UNSIGNED NOT NULL,
    ereignis      VARCHAR(30)  NOT NULL,
    zeitpunkt     DATETIME     NOT NULL,
    ip            VARCHAR(45)  NULL,
    quelle        VARCHAR(30)  NULL,
    benutzer_id   INT UNSIGNED NULL,
    details       TEXT         NULL,                         -- JSON
    erstellt_am   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_nlev_empf (empfaenger_id),
    CONSTRAINT fk_nlev_empf FOREIGN KEY (empfaenger_id) REFERENCES newsletter_empfaenger (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recht: Newsletter pflegen (Babsi), wie slider.verwalten
INSERT IGNORE INTO berechtigungen (name, beschreibung, aktiv) VALUES ('newsletter.verwalten', 'newsletter: empfänger, inhalte und versand', 1);
INSERT IGNORE INTO rollen_berechtigungen (rolle_id, berechtigung_id)
SELECT r.id, b.id FROM rollen r JOIN berechtigungen b ON b.name = 'newsletter.verwalten'
WHERE r.name IN ('superadmin', 'admin', 'assistent', 'manager');
