-- 259: Herstellerkürzel max. 3 Zeichen + weitere Namen für Importe (Jacky 2026-11-22)
--
-- Kürzel: längere machen die Artikelnummer unnötig lang (PRY-211-0001), daher hart auf 3 begrenzt.
--
-- weitere_namen: wie der Hersteller in fremden Quellen heißt, durch ";" getrennt. Beispiel: JTL liefert
-- "DROPS", im ERP heißt er "DROPS Design" -> bisher bei jedem JTL-Import "Hersteller unbekannt".
-- JTL-, Lang- und Standard-CSV-Import suchen über HerstellerRepository::namensIndex() in Name + weitere_namen.
-- Wiederholbar.

UPDATE hersteller SET kuerzel = LEFT(kuerzel, 3) WHERE CHAR_LENGTH(kuerzel) > 3;

ALTER TABLE hersteller
    MODIFY COLUMN kuerzel VARCHAR(3) NULL,
    ADD COLUMN IF NOT EXISTS weitere_namen VARCHAR(255) NULL AFTER kuerzel;

UPDATE hersteller
SET weitere_namen = 'DROPS'
WHERE name = 'DROPS Design' AND (weitere_namen IS NULL OR weitere_namen = '');
