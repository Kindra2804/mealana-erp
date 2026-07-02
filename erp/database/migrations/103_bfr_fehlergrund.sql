-- Migration 103: Fehlergrund direkt am Beleg für die Nacherfassungs-Seite

ALTER TABLE kassen_bons
    ADD COLUMN bfr_fehlergrund VARCHAR(255) NULL AFTER bfr_status;

ALTER TABLE bfr_nullbelege
    ADD COLUMN bfr_fehlergrund VARCHAR(255) NULL AFTER bfr_status;
