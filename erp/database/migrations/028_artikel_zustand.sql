ALTER TABLE artikel
    ADD COLUMN zustand VARCHAR(30) NOT NULL DEFAULT 'neu'
    AFTER aktiv;
