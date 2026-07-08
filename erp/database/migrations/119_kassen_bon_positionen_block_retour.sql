-- 'retour' fehlte im ENUM von kassen_bon_positionen.block, seit das Feature 2026-06-29
-- gebaut wurde. MySQL speichert einen ungültigen ENUM-Wert im non-strict Modus lautlos
-- als leeren String statt eines Fehlers -- Retour-Zeilen wurden dadurch nie korrekt als
-- 'retour' markiert (Druck-Kennzeichnung, Umsatzzähler-/Zahlungsverlauf-Berechnung
-- betroffen).
ALTER TABLE kassen_bon_positionen
    MODIFY COLUMN block ENUM('auftrag','addon','storno','retour') NULL;

-- Bereits vorhandene Fehlbuchungen korrigieren: eine Zeile mit negativer Menge und
-- leerem block gehört garantiert zu einer Retoure (normale Verkaufszeilen sind nie negativ).
UPDATE kassen_bon_positionen
SET block = 'retour'
WHERE block = '' AND menge < 0;
