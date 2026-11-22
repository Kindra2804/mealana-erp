-- 263: Bareingang zu einer JTL-Rechnung an der Kasse (Jacky 2026-11-19/22)
--
-- Solange mealana.at noch auf JTL läuft, kommen Kunden mit einer JTL-Rechnung (Abholung, Überweisung
-- vergessen …) in den Laden und zahlen bar/Karte. Der Zahlbeleg "💶 Rechnung bezahlen" braucht einen
-- ERP-Auftrag – JTL-Rechnungen gibt es im ERP aber nicht. Darum: eigene Bon-Zeile block 'zahlung', 0 %
-- (die USt steht auf der JTL-Rechnung), RKSV-signiert, ohne Auftrag, mit der JTL-Rechnungsnummer hier.
--
-- Buchhaltungs-Export: Kassa/Bank an das Gegenkonto der Pseudo-Zahlungsart 'jtl_verrechnung'
-- (Buchhaltung → Zahlungsart-Konten; welches Konto, klärt Jacky mit der Steuerberatung).
-- Ohne Konto erscheint der Betrag als Hinweis "manuell buchen". Wiederholbar.

ALTER TABLE kassen_bon_positionen
    ADD COLUMN IF NOT EXISTS fremd_beleg_nr VARCHAR(40) NULL AFTER web_auftrag_id;

INSERT INTO zahlungsart_konten (zahlungsart, konto_id, hinweis)
SELECT 'jtl_verrechnung', NULL, 'Bareingang zu JTL-Rechnung an der Kasse: Gegenkonto (Verrechnung/Debitor JTL, mit Steuerberatung klären)'
WHERE NOT EXISTS (SELECT 1 FROM zahlungsart_konten WHERE zahlungsart = 'jtl_verrechnung');
