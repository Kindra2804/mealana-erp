-- Migration 128: Kontonummern an echten Kontenrahmen (Steuerberater) angepasst,
-- Kernkonten korrigiert (waren nur Platzhalter-Annahmen), 13%/4,9% Steuersaetze ergaenzt.

-- Erlöskonten der bestehenden Artikelgruppen auf die von Babsi final festgelegten Nummern
UPDATE artikel_gruppen SET konto_nr = '4010' WHERE konto_nr = '4100'; -- Nadeln
UPDATE artikel_gruppen SET konto_nr = '4020' WHERE konto_nr = '4200'; -- Stoffe Nähen
UPDATE artikel_gruppen SET konto_nr = '4025' WHERE konto_nr = '4250'; -- Knöpfe
UPDATE artikel_gruppen SET konto_nr = '4030' WHERE konto_nr = '4300'; -- Sticken
UPDATE artikel_gruppen SET konto_nr = '4035' WHERE konto_nr = '4350'; -- Spinnen/Weben
UPDATE artikel_gruppen SET konto_nr = '4040' WHERE konto_nr = '4400'; -- Sonstiges Zubehör
UPDATE artikel_gruppen SET konto_nr = '4050' WHERE konto_nr = '4500'; -- Hefte Bücher Anleitungen
UPDATE artikel_gruppen SET konto_nr = '4060' WHERE konto_nr = '4600'; -- Verkaufshilfen
UPDATE artikel_gruppen SET konto_nr = '4090' WHERE konto_nr = '4900'; -- Versandkosten
-- Wolle bleibt 4000 (unveraendert)

-- Gutscheine sind kein Erlöskonto (Anzahlung, kein steuerpflichtiger Umsatz beim Verkauf) —
-- deaktiviert bis das Gutschein-Modul selbst gebaut wird und direkt auf 3203 bucht.
UPDATE artikel_gruppen SET aktiv = 0 WHERE name = 'Gutscheine';

-- Neue Artikelgruppen (noch nicht vorhanden)
INSERT INTO artikel_gruppen (konto_nr, name, aktiv, sortierung) VALUES
    ('4065', 'Messe', 1, 65),
    ('4070', 'Workshops', 1, 70);

-- Kontenplan: Kernkonten komplett neu (die bisherigen 1500/1600/2500/2700-Platzhalter waren falsch)
DELETE FROM kontenplan WHERE typ IN ('bank', 'kasse', 'steuer');
INSERT INTO kontenplan (kontonummer, name, typ) VALUES
    ('2700', 'Kassa', 'kasse'),
    ('2800', 'Bank', 'bank'),
    ('2510', 'Vorsteuer 10%', 'steuer'),
    ('2513', 'Vorsteuer 13%', 'steuer'),
    ('2520', 'Vorsteuer 20%', 'steuer'),
    ('3510', 'Umsatzsteuer 10%', 'steuer'),
    ('3513', 'Umsatzsteuer 13%', 'steuer'),
    ('3520', 'Umsatzsteuer 20%', 'steuer'),
    ('3203', 'Erhaltene Anzahlungen 0% Gutschein', 'steuer');

-- Erlöskonten in kontenplan aus artikel_gruppen neu ziehen (Nummern haben sich geändert)
DELETE FROM kontenplan WHERE typ = 'erloes';
INSERT INTO kontenplan (kontonummer, name, typ, aktiv)
SELECT konto_nr, name, 'erloes', aktiv FROM artikel_gruppen;

-- Steuersätze: 13% und 4,9% ergänzen (RKSV/BFR kennt 5 Gruppen A-E), MeaLana nutzt sie
-- aktuell nicht selbst, aber fuer Weitergabe an andere Betriebe vorbereiten.
INSERT INTO steuerklassen (name, satz, land, aktiv) VALUES
    ('Ermäßigter Steuersatz 13%', 13.00, 'AT', 0),
    ('Sonder-Steuersatz 4,9%', 4.90, 'AT', 0);
