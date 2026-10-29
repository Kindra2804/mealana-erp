-- Migration 212: Z-Bon mit eigenem Nummernkreis je Kasse (Jacky 2026-10-29)
--
-- Bisher zogen X- und Z-Bons ihre Nummer aus dem Belegzähler der Verkaufsbons
-- (K1-Z-2026-000050) → Lücken in den Verkaufsbelegen, Z-Bons schwer auffindbar.
-- Neu (KassenService::naechsteZBonNr / xBonKennung):
--   Z-Bon: K1-Z-00001 — fortlaufend je Kasse, ohne Jahr
--   X-Bon: K1-X-20261029-201343 — keine Nummer, nur Kasse + Zeitpunkt
-- Bestehende Z-Bons werden je Kasse in Tagesreihenfolge neu nummeriert, X-Bons umbenannt.

UPDATE kassen_bons b
JOIN (
    SELECT zb.id, k.kasse_nr,
           ROW_NUMBER() OVER (PARTITION BY zb.kasse_id ORDER BY a.datum, zb.id) AS rn
    FROM kassen_bons zb
    JOIN kassen k ON k.id = zb.kasse_id
    LEFT JOIN kassen_abschluesse a ON a.bon_id = zb.id
    WHERE zb.typ = 'z_bon'
) x ON x.id = b.id
SET b.bon_nr = CONCAT(x.kasse_nr, '-Z-', LPAD(x.rn, 5, '0'));

UPDATE kassen_bons b
JOIN kassen k ON k.id = b.kasse_id
SET b.bon_nr = CONCAT(k.kasse_nr, '-X-', DATE_FORMAT(b.erstellt_am, '%Y%m%d-%H%i%s'))
WHERE b.typ = 'x_bon';
