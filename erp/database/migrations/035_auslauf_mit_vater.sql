ALTER TABLE artikel
    ADD COLUMN auslauf_mit_vater TINYINT(1) NOT NULL DEFAULT 0
    AFTER ist_auslaufartikel;
