ALTER TABLE artikel
    ADD COLUMN zustand_vater_id INT UNSIGNED NULL AFTER zustand,
    ADD INDEX idx_zustand_vater (zustand_vater_id);

ALTER TABLE artikel
    ADD CONSTRAINT fk_zustand_vater
        FOREIGN KEY (zustand_vater_id) REFERENCES artikel(id) ON DELETE SET NULL;
