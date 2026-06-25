-- Migration 069: Firmendaten + SMTP in system_einstellungen; mahnungen-Tabelle
-- Alle INSERT IGNORE damit bestehende Werte nicht überschrieben werden.

-- Firmendaten (für PDF-Header + Footer)
INSERT IGNORE INTO system_einstellungen (schluessel, wert) VALUES
    ('firmenname',    'MEALANA KG'),
    ('strasse',       ''),
    ('plz',           ''),
    ('ort',           ''),
    ('land',          'Österreich'),
    ('telefon',       ''),
    ('fax',           ''),
    ('email',         ''),
    ('website',       ''),
    ('uid_nummer',    ''),
    ('steuernummer',  ''),
    ('bank_name',     ''),
    ('iban',          ''),
    ('bic',           ''),
    ('logo_pfad',     '');

-- SMTP / Mail
INSERT IGNORE INTO system_einstellungen (schluessel, wert) VALUES
    ('mail_smtp_host',       ''),
    ('mail_smtp_port',       '587'),
    ('mail_smtp_user',       ''),
    ('mail_smtp_pass',       ''),
    ('mail_smtp_encryption', 'tls'),
    ('mail_from_name',       'MEALANA KG'),
    ('mail_from_address',    ''),
    ('mail_aktiv',           '0');

-- Mahnwesen-Protokoll
CREATE TABLE IF NOT EXISTS mahnungen (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auftrag_id   INT NOT NULL,
    typ          ENUM('erinnerung', 'stornierung') NOT NULL,
    gesendet_am  DATETIME NOT NULL DEFAULT NOW(),
    mail_an      VARCHAR(255) NULL,
    erstellt_von ENUM('cronjob', 'manuell') NOT NULL DEFAULT 'cronjob',
    INDEX idx_auftrag (auftrag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
