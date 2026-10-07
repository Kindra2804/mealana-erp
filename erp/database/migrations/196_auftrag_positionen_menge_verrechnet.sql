-- Belege-Umbau Stufe 2 (Jacky 2026-10-07): Menge, die bereits auf einem Beleg steht —
-- auf einer (Teil-)Rechnung ODER auf einem Kassenbon. Gemeinsamer Zähler wie
-- menge_retourniert/menge_gutgeschrieben: eine Rechnung nimmt nur, was noch nicht
-- verrechnet ist, und ein Bon erhöht ihn genauso -> nie Bon UND Rechnung für dieselbe Ware.
ALTER TABLE auftrag_positionen
    ADD COLUMN menge_verrechnet INT NOT NULL DEFAULT 0 AFTER menge_gutgeschrieben;

-- Altbestand 1: bestehende (nicht stornierte) Rechnungen
UPDATE auftrag_positionen p
JOIN (
    SELECT rp.auftrag_position_id, SUM(rp.menge) AS m
    FROM rechnung_positionen rp
    JOIN rechnungen r ON r.id = rp.rechnung_id AND r.storniert = 0
    WHERE rp.auftrag_position_id IS NOT NULL
    GROUP BY rp.auftrag_position_id
) x ON x.auftrag_position_id = p.id
SET p.menge_verrechnet = x.m;

-- Altbestand 2: Kassen-Aufträge (Spiegel eines Bons) und JTL-Archiv gelten als verrechnet
UPDATE auftrag_positionen p
JOIN auftraege a ON a.id = p.auftrag_id
SET p.menge_verrechnet = GREATEST(p.menge, 0)
WHERE a.kanal IN ('kasse', 'jtl_archiv');

-- Altbestand 3: Web-/manuelle Aufträge, die an der Kasse bezahlt wurden (Bon = Beleg)
UPDATE auftrag_positionen p
JOIN auftraege a ON a.id = p.auftrag_id
SET p.menge_verrechnet = GREATEST(p.menge_verrechnet, LEAST(p.menge, GREATEST(p.menge_geliefert, p.menge_abgeholt)))
WHERE a.kassen_bon_id IS NOT NULL AND a.kanal NOT IN ('kasse', 'jtl_archiv');
