-- Versandkosten fehlten bisher in auftraege.netto/steuer/bruttobetrag (nur angezeigt,
-- nie mitgerechnet) -- offener Betrag, Zahlung buchen und Mahnungen waren um die
-- Versandkosten zu niedrig. Ab jetzt rechnet AuftragService den Versand ein (brutto,
-- Steuersatz der überwiegenden Leistung, siehe src/modules/auftraege/Versandsteuer.php).
--
-- Bestehende Aufträge nachziehen, gleiche Regel wie Versandsteuer::satz():
-- Satz mit der größten Bruttosumme der Positionen (Gleichstand -> höherer Satz,
-- 0-%-Positionen zählen nicht, keine -> 20 %).
-- NUR Aufträge ohne gültige Rechnung (ausgestellte Rechnungen bleiben wie sie sind,
-- Korrektur dort nur per Gutschrift + neuer Rechnung) und nur, wenn der Betrag den
-- Versand nachweislich noch NICHT enthält (bruttobetrag = Summe der Positionen).
UPDATE auftraege a
JOIN (
    SELECT p.auftrag_id,
           ROUND(SUM(p.gesamtpreis_netto), 2)                                           AS pos_netto,
           ROUND(SUM(ROUND(p.gesamtpreis_netto * p.steuer_prozent / 100, 2)), 2)        AS pos_steuer,
           COALESCE((
               SELECT p2.steuer_prozent FROM auftrag_positionen p2
               WHERE p2.auftrag_id = p.auftrag_id AND p2.steuer_prozent > 0
               GROUP BY p2.steuer_prozent
               ORDER BY SUM(p2.gesamtpreis_netto * (1 + p2.steuer_prozent / 100)) DESC, p2.steuer_prozent DESC
               LIMIT 1
           ), 20)                                                                        AS versand_satz
    FROM auftrag_positionen p
    GROUP BY p.auftrag_id
) s ON s.auftrag_id = a.id
SET a.nettobetrag  = s.pos_netto + ROUND(a.versandkosten / (1 + s.versand_satz / 100), 2),
    a.steuerbetrag = s.pos_steuer + (a.versandkosten - ROUND(a.versandkosten / (1 + s.versand_satz / 100), 2)),
    a.bruttobetrag = s.pos_netto + s.pos_steuer + a.versandkosten
WHERE a.versandkosten > 0
  AND ABS(a.bruttobetrag - (s.pos_netto + s.pos_steuer)) < 0.01
  AND NOT EXISTS (SELECT 1 FROM rechnungen r WHERE r.auftrag_id = a.id AND r.storniert = 0);
