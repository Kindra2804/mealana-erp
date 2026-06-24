-- Preis zu Versandklassen hinzufügen

ALTER TABLE versandklassen 
ADD COLUMN preis_brutto DECIMAL(10,2) NULL;

INSERT INTO versandklassen (`name`, `code`, `kuerzel`, `sortierung`, `preis_brutto`) VALUES
('Standardversand mit Post AT', 'SAT', 'Std. AT', '1', '6.50'),
('Versand + Teillieferung mit Post AT', 'TLAT', 'Std. TL AT', '2', '9.50'),
('Nachnahme Post AT', 'NN', 'NN AT', '3', '13'),
('Standardversand DE', 'SDE', 'Std. DE', '4', '9.90'),
('Standardversand IT/HU', 'SEU', 'Std. EU', '5', '15.90');
