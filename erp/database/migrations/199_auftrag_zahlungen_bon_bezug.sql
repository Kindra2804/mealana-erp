-- Belege-Umbau Stufe 4 (Buchhaltung, 2026-10-07): an der Kasse gebuchte Auftrags-Zahlungen
-- ("Bezahlt an der Kasse — Bon ...", "Rückerstattung ... Bon ...") hatten nur die Bon-Nr.
-- in der Notiz. Der Export bucht Kassengeld über den Bon -- diese Zahlungen dürfen nicht
-- zusätzlich als Zahlungseingang (Bank an Kunde) laufen. Dafür braucht es den echten Bezug.
UPDATE auftrag_zahlungen z
JOIN kassen_bons b ON z.notiz LIKE CONCAT('%Bon ', b.bon_nr, '%')
SET z.kassen_bon_id = b.id
WHERE z.kassen_bon_id IS NULL;

-- Zahlungsweg für den Altbestand (Anzeige in der Zahlungsinfo der Rechnung)
UPDATE auftrag_zahlungen z
JOIN kassen_bons b ON b.id = z.kassen_bon_id
SET z.zahlungsweg = CASE b.zahlungsart WHEN 'bar' THEN 'bar' WHEN 'karte_extern' THEN 'karte'
                                       WHEN 'gutschein' THEN 'gutschein' ELSE 'sonstig' END
WHERE z.zahlungsweg IS NULL;

UPDATE auftrag_zahlungen z
JOIN auftraege a ON a.id = z.auftrag_id
SET z.zahlungsweg = CASE a.zahlungsart WHEN 'vorkasse' THEN 'ueberweisung' WHEN 'rechnung' THEN 'ueberweisung'
                                       WHEN 'paypal' THEN 'paypal' WHEN 'nachnahme' THEN 'nachnahme'
                                       WHEN 'bar' THEN 'bar' ELSE 'sonstig' END
WHERE z.zahlungsweg IS NULL AND z.kassen_bon_id IS NULL;
