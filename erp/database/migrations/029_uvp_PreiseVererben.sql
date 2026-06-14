ALTER TABLE artikel
    ADD COLUMN uvp             DECIMAL(8,2) NULL        AFTER zustand,
    ADD COLUMN preise_vererben TINYINT(1)   NOT NULL DEFAULT 0 AFTER uvp;
