-- 252: Newsletter Schritt 4 — Versand (Jacky 2026-11-15)
-- Beim Start wird der Newsletter „eingefroren“ (vorlage: HTML/Text mit und ohne Vorname, Bilder schon auf der
-- Belegseite, Links als anonyme Zähl-Links). Der Cron newsletter_versand.php (alle 5 Min.) arbeitet die
-- Warteschlange newsletter_versand ab — höchstens newsletter_max_pro_stunde Mails je Postfach (SMTP-Konto).
-- Klickzähler: nur Summe je Link (newsletter_links.klicks), kein Bezug zu Empfängern (keine Einwilligung nötig).
-- Wiederholbar.

ALTER TABLE newsletter
    ADD COLUMN IF NOT EXISTS vorlage            LONGTEXT     NULL AFTER bausteine,   -- JSON {html_mit, html_ohne, text_mit, text_ohne}
    ADD COLUMN IF NOT EXISTS versand_start_am   DATETIME     NULL AFTER testmail_am,
    ADD COLUMN IF NOT EXISTS versand_ende_am    DATETIME     NULL AFTER versand_start_am,
    ADD COLUMN IF NOT EXISTS versand_pausiert   TINYINT(1)   NOT NULL DEFAULT 0 AFTER versand_ende_am,
    ADD COLUMN IF NOT EXISTS versand_von        INT UNSIGNED NULL AFTER versand_pausiert,
    ADD COLUMN IF NOT EXISTS klicks             INT UNSIGNED NOT NULL DEFAULT 0 AFTER versand_von,
    ADD COLUMN IF NOT EXISTS klicks_am          DATETIME     NULL AFTER klicks;

CREATE TABLE IF NOT EXISTS newsletter_versand (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    newsletter_id  INT UNSIGNED NOT NULL,
    empfaenger_id  INT UNSIGNED NOT NULL,
    status         ENUM('offen','gesendet','fehler','uebersprungen') NOT NULL DEFAULT 'offen',
    versuche       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    gesendet_am    DATETIME     NULL,
    fehler         VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_nlv (newsletter_id, empfaenger_id),
    KEY idx_nlv_status (newsletter_id, status),
    KEY idx_nlv_zeit (gesendet_am),
    CONSTRAINT fk_nlv_nl FOREIGN KEY (newsletter_id) REFERENCES newsletter (id) ON DELETE CASCADE,
    CONSTRAINT fk_nlv_empf FOREIGN KEY (empfaenger_id) REFERENCES newsletter_empfaenger (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter_links (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    newsletter_id  INT UNSIGNED NOT NULL,
    schluessel     CHAR(16)     NOT NULL,                -- steht im Zähl-Link der Belegseite (?k=…)
    url            VARCHAR(1000) NOT NULL,
    klicks         INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_nll_schluessel (schluessel),
    KEY idx_nll_nl (newsletter_id),
    CONSTRAINT fk_nll_nl FOREIGN KEY (newsletter_id) REFERENCES newsletter (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Höchstzahl Newsletter-Mails pro Stunde und Postfach (Hosting-Grenze, JTL hatte 100)
INSERT IGNORE INTO system_einstellungen (schluessel, wert) VALUES ('newsletter_max_pro_stunde', '100');
