ALTER TABLE artikel
ADD COLUMN ueberverkauf_erlaubt TINYINT(1) NOT NULL DEFAULT 0 AFTER ist_auslaufartikel;