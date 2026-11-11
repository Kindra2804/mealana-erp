-- 229: Mail-Absender + SMTP je Kanal (Jacky 2026-11-09/11)
-- Jeder Shop verschickt seine Kundenmails (Auftragsbestätigung, Rechnung, Versand, Zahlung, Mahnung …)
-- unter der eigenen Adresse, z. B. bestellung@sockenwolle-online.at statt der Hauptadresse.
-- Leere Felder = globaler Standard aus Einstellungen → Mail (system_einstellungen mail_*).
-- Eigenes SMTP greift nur, wenn mail_smtp_host gesetzt ist; dann gelten alle SMTP-Felder des Shops.
-- mail_smtp_encryption: 'tls' | 'ssl' | '' (keine), NULL = wie global.
-- Wiederholbar (IF NOT EXISTS, MariaDB). Das Passwort liegt wie die Woo-Schlüssel und das globale SMTP-Passwort im Klartext in der DB.

ALTER TABLE shops
    ADD COLUMN IF NOT EXISTS mail_from_name       VARCHAR(100) NULL DEFAULT NULL AFTER widerrufe_ungeklaert,
    ADD COLUMN IF NOT EXISTS mail_from_address    VARCHAR(190) NULL DEFAULT NULL AFTER mail_from_name,
    ADD COLUMN IF NOT EXISTS mail_smtp_host       VARCHAR(190) NULL DEFAULT NULL AFTER mail_from_address,
    ADD COLUMN IF NOT EXISTS mail_smtp_port       SMALLINT UNSIGNED NULL DEFAULT NULL AFTER mail_smtp_host,
    ADD COLUMN IF NOT EXISTS mail_smtp_user       VARCHAR(190) NULL DEFAULT NULL AFTER mail_smtp_port,
    ADD COLUMN IF NOT EXISTS mail_smtp_pass       VARCHAR(255) NULL DEFAULT NULL AFTER mail_smtp_user,
    ADD COLUMN IF NOT EXISTS mail_smtp_encryption VARCHAR(5)   NULL DEFAULT NULL AFTER mail_smtp_pass;
