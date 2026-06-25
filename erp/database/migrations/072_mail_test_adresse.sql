-- Migration 072: Mailer Test-Adresse
-- Wenn mail_aktiv=0 und mail_test_adresse gesetzt → Mail geht an Test-Adresse statt verworfen zu werden

INSERT IGNORE INTO system_einstellungen (schluessel, wert)
VALUES ('mail_test_adresse', '');
