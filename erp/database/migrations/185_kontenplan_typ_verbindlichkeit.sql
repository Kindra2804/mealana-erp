-- Gutschein-Konto 3230 "Erhaltene Anzahlungen" ist kein Steuerkonto, sondern eine
-- Verbindlichkeit (Geld gehört bis zur Einlösung dem Kunden). Als Typ 'steuer' tauchte
-- es in der Steuerklassen-Zuordnung als wählbares USt-Konto auf -- eigener Typ dafür.
ALTER TABLE kontenplan
    MODIFY typ ENUM('erloes','aufwand','steuer','bank','kasse','verbindlichkeit') NOT NULL;

UPDATE kontenplan SET typ = 'verbindlichkeit' WHERE kontonummer = '3230';
