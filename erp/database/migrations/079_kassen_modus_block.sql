-- Migration 079: Kassen-Konfiguration erweitern + Bon-Block-Typ
-- kassen: modus (online/offline) + rksv_kassen_id für Geräte-Binding
-- kassen_bon_positionen: block (auftrag/addon/storno) für strukturierte Bons

ALTER TABLE kassen
    ADD COLUMN modus         ENUM('online','offline') NOT NULL DEFAULT 'online' AFTER lager_id,
    ADD COLUMN rksv_kassen_id VARCHAR(50) NULL AFTER modus;

UPDATE kassen SET rksv_kassen_id = CONCAT('RKSV-', UPPER(kasse_nr)) WHERE rksv_kassen_id IS NULL;

ALTER TABLE kassen_bon_positionen
    ADD COLUMN block ENUM('auftrag','addon','storno') NULL DEFAULT NULL AFTER bon_id;
