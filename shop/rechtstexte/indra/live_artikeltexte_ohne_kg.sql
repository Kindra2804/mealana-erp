-- indra-design → Einzelunternehmen Barbara Indra (Umstellung 14.11.2026)
-- Entfernt in den Artikelbeschreibungen den Satzteil " – ein Label der MEALANA KG."
-- (wird zu "… in Wiener Neustadt."). Der Gedankenstrich steht als Byte-Code 0xE28093,
-- damit es unabhängig vom Zeichensatz des SQL-Werkzeugs funktioniert.
-- aktualisiert_am ändert sich automatisch → der Shop-Sync überträgt die Artikel neu.
-- Auf Dev getestet am 14.11.2026: 118 Artikel geändert, danach 0 Treffer.

-- 1) Vorher zählen
SELECT COUNT(*) AS betroffen FROM artikel WHERE beschreibung LIKE '%Label der MEALANA KG%';

-- 2) Ersetzen
UPDATE artikel
   SET beschreibung = REPLACE(beschreibung,
                              CONCAT(' ', CONVERT(0xE28093 USING utf8mb4), ' ein Label der MEALANA KG.'),
                              '.')
 WHERE beschreibung LIKE '%Label der MEALANA KG%';

-- 2b) Manche Texte haben den Gedankenstrich als HTML-Zeichen &ndash; (Live: 25 Stück, v.a. Weihnachtsanhänger XM)
UPDATE artikel
   SET beschreibung = REPLACE(beschreibung, ' &ndash; ein Label der MEALANA KG.', '.')
 WHERE beschreibung LIKE '%&ndash; ein Label der MEALANA KG%';

-- 3) Nachher prüfen (soll 0 sein)
SELECT COUNT(*) AS rest FROM artikel WHERE beschreibung LIKE '%MEALANA KG%';
