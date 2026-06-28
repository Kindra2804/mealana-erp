-- zahlungsart ENUM um 'nachnahme' erweitern
-- Nachnahme = Kunde zahlt bar beim Postboten, wir erhalten Betrag über Post
ALTER TABLE auftraege
MODIFY COLUMN zahlungsart ENUM('vorkasse','paypal','rechnung','bar','nachnahme','gutschein','gemischt') NOT NULL DEFAULT 'vorkasse';
