-- Gutschein-Zeilen am Kassenbon (block 'gutschein_kauf' = Gutschein verkauft,
-- 'gutschein_verkauf' = Retoure als Gutschein ausgegeben) kannte das ENUM nicht --
-- MariaDB speicherte sie (nicht-strikter Modus) als '' und der Bon-Druck zeigte die
-- Zeile gar nicht an. ENUM erweitern, bestehende ''-Zeilen nachziehen.
ALTER TABLE kassen_bon_positionen
    MODIFY block ENUM('auftrag','addon','storno','retour','gutschein_kauf','gutschein_verkauf') NULL;

UPDATE kassen_bon_positionen p
JOIN artikel a ON a.id = p.artikel_id
SET p.block = IF(p.bezeichnung = 'Gutschein-Verkauf', 'gutschein_verkauf', 'gutschein_kauf')
WHERE p.block = '' AND a.ist_gutschein = 1;

UPDATE kassen_bon_positionen SET block = NULL WHERE block = '';
