ALTER TABLE artikel 
    ADD COLUMN ist_vater TINYINT(1) NOT NULL DEFAULT 0;

UPDATE artikel 
SET ist_vater = 1 
WHERE id IN (SELECT DISTINCT artikel_id FROM artikel_varianten);

ALTER TABLE lagerbestand
    ADD COLUMN artikel_id INT UNSIGNED NULL,
    ADD CONSTRAINT fk_lb_artikel_id FOREIGN KEY (artikel_id)
    REFERENCES artikel(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

ALTER TABLE lagerbestand
    ADD CONSTRAINT chk_lb_genau_eine_referenz CHECK (
        (artikel_varianten_id IS NOT NULL AND artikel_id IS NULL) OR
        (artikel_varianten_id IS NULL  AND artikel_id IS NOT NULL)
    );

ALTER TABLE lagerbestand
ADD UNIQUE KEY uq_lb_artikel_variante (artikel_id, lager_id);

ALTER TABLE lager_bewegungen
    MODIFY COLUMN artikel_varianten_id INT UNSIGNED NULL,
    ADD COLUMN artikel_id INT UNSIGNED NULL,
    ADD CONSTRAINT fk_lbew_artikel_id FOREIGN KEY (artikel_id)
    REFERENCES artikel(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
    ADD CONSTRAINT chk_lbew_genau_eine_referenz CHECK (
        (artikel_varianten_id IS NOT NULL AND artikel_id IS NULL) OR
        (artikel_varianten_id IS NULL  AND artikel_id IS NOT NULL)
    );

