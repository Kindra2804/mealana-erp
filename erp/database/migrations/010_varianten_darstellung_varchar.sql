-- Migration 010: varianten_darstellung ENUM → VARCHAR(50)
-- ENUM-Werte bleiben identisch, keine Datenmigration nötig.

ALTER TABLE `artikel`
    MODIFY COLUMN `varianten_darstellung` VARCHAR(50) NOT NULL DEFAULT 'swatches';
