-- Shop-Import errechnete den Steuersatz aus gerundeten Beträgen (tax/total) -> 20,03 %
-- statt 20 %. Folge: Buchhaltungsexport fand kein USt-Konto, Auftragsbetrag um Cents
-- daneben. Import rastet jetzt auf echte Sätze ein (ShopBestellungSyncService::
-- echterSteuersatz); hier die bestehenden Shop-Positionen nachziehen (max. 0,5 %-Punkte).
UPDATE auftrag_positionen p
JOIN auftraege a ON a.id = p.auftrag_id
JOIN (SELECT DISTINCT satz FROM steuerklassen) s
  ON ABS(s.satz - p.steuer_prozent) <= 0.5 AND ABS(s.satz - p.steuer_prozent) > 0
SET p.steuer_prozent = s.satz
WHERE a.kanal = 'woocommerce';

-- Beträge dieser Aufträge neu rechnen (Positionen + Versand brutto, Versandsatz = Satz
-- der überwiegenden Leistung, gleiche Regel wie Versandsteuer.php / Migration 181) --
-- nur ohne gültige Rechnung, ausgestellte Rechnungen bleiben wie sie sind.
UPDATE auftraege a
JOIN (
    SELECT p.auftrag_id,
           ROUND(SUM(p.gesamtpreis_netto), 2)                                    AS pos_netto,
           ROUND(SUM(ROUND(p.gesamtpreis_netto * p.steuer_prozent / 100, 2)), 2) AS pos_steuer,
           COALESCE((
               SELECT p2.steuer_prozent FROM auftrag_positionen p2
               WHERE p2.auftrag_id = p.auftrag_id AND p2.steuer_prozent > 0
               GROUP BY p2.steuer_prozent
               ORDER BY SUM(p2.gesamtpreis_netto * (1 + p2.steuer_prozent / 100)) DESC, p2.steuer_prozent DESC
               LIMIT 1
           ), 20) AS versand_satz
    FROM auftrag_positionen p
    GROUP BY p.auftrag_id
) s ON s.auftrag_id = a.id
SET a.nettobetrag  = s.pos_netto + ROUND(a.versandkosten / (1 + s.versand_satz / 100), 2),
    a.steuerbetrag = s.pos_steuer + (a.versandkosten - ROUND(a.versandkosten / (1 + s.versand_satz / 100), 2)),
    a.bruttobetrag = s.pos_netto + s.pos_steuer + a.versandkosten
WHERE a.kanal = 'woocommerce'
  AND NOT EXISTS (SELECT 1 FROM rechnungen r WHERE r.auftrag_id = a.id AND r.storniert = 0);
