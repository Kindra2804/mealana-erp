-- Gutscheine (Mehrzweckgutscheine = erhaltene Anzahlung ohne USt) laufen über Konto
-- 3230 (Babsi 2026-09-30). Der Kontenplan hatte dafür "3203 Erhaltene Anzahlungen 0%
-- Gutschein" (Zahlendreher) -- umnummerieren statt neu anlegen, damit die bestehende
-- Zuordnung der Zahlungsart "gutschein" (Einlösung) automatisch mitzieht.
UPDATE kontenplan SET kontonummer = '3230'
WHERE kontonummer = '3203'
  AND NOT EXISTS (SELECT 1 FROM (SELECT kontonummer FROM kontenplan) k WHERE k.kontonummer = '3230');

-- Falls 3203 nicht existierte (andere Installation): Konto neu anlegen
INSERT INTO kontenplan (kontonummer, name, typ, aktiv)
SELECT '3230', 'Erhaltene Anzahlungen 0% Gutschein', 'steuer', 1
WHERE NOT EXISTS (SELECT 1 FROM (SELECT kontonummer FROM kontenplan) k WHERE k.kontonummer = '3230');

-- Zahlungsart "gutschein" (Einlösung) auf 3230, falls noch nicht zugeordnet
UPDATE zahlungsart_konten zk
JOIN kontenplan k ON k.kontonummer = '3230'
SET zk.konto_id = k.id
WHERE zk.zahlungsart = 'gutschein' AND zk.konto_id IS NULL;

-- Artikelgruppe "Gutscheine" (Verkauf): statt Erlöskonto 4700 auf das Anzahlungskonto
UPDATE artikel_gruppen SET konto_nr = '3230' WHERE konto_nr = '4700';
