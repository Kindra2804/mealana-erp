-- Lieferart bei Aufträgen hinzufügen um zu unterscheiden ob Ware verschickt oder abgeholt wird

ALTER TABLE auftraege 
ADD COLUMN lieferart ENUM('versand','abholung') NOT NULL DEFAULT 'versand';