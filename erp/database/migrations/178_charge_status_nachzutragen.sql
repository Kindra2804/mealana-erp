-- Chargenpflichtige Artikel mit Bestand ohne Charge standen auf charge_status
-- 'unbekannt' (JTL-Lagerbestand-Import vom 2026-08-11, direkt eingespielt ohne
-- LagerService). Dadurch fehlten sie in der Charge-Nachtragsliste (filtert auf
-- 'nachzutragen'). Gleiche Regel wie LagerService::wareneingang(): Pflicht + keine
-- Charge = 'nachzutragen'. Nur Zeilen MIT Bestand -- die tausenden Leerzeilen
-- (Bestand <= 0) aus demselben Import haben nichts nachzutragen.
UPDATE lagerbestand lb
JOIN artikel a ON a.id = lb.artikel_id
SET lb.charge_status = 'nachzutragen'
WHERE a.charge_pflicht = 1
  AND lb.charge IS NULL
  AND lb.charge_status = 'unbekannt'
  AND lb.bestand > 0;
