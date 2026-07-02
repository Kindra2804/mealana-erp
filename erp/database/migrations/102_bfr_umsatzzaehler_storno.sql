-- Migration 102: Gesamtumsatzzähler pro Kasse (Vorgabe BFR-Hersteller: darf nie negativ werden)

ALTER TABLE kassen
    ADD COLUMN bfr_umsatzzaehler DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER bfr_url;

UPDATE kassen k
SET bfr_umsatzzaehler = (
    SELECT COALESCE(SUM(bruttobetrag), 0)
    FROM kassen_bons
    WHERE kasse_id = k.id AND bfr_status = 'signiert'
);
