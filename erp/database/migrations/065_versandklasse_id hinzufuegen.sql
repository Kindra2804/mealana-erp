-- tabelle aufträge bekommt noch VersandklassenID für spätere Auswertungen

ALTER TABLE auftraege 
ADD COLUMN versandklasse_id INT UNSIGNED NULL,
ADD CONSTRAINT fk_auftr_versandklassen
FOREIGN KEY (versandklasse_id) REFERENCES versandklassen (id) ON UPDATE CASCADE; 
