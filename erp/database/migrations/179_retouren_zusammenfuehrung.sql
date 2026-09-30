-- Retouren-Zusammenführung (2026-09-30): Kasse, Packplatz-Retoure und ERP-Gutschrift
-- teilen sich jetzt dieselben Zähler und denselben Rücklagerungs-Weg.

-- 1) Getrennte Zähler: menge_retourniert = Ware ist (physisch) zurück,
--    menge_gutgeschrieben = Geld/Gutschein wurde erstattet. Getrennt, weil z.B. eine
--    Ersatzlieferung Ware zurücknimmt, ohne etwas gutzuschreiben. Bisher zählte nur
--    die Kasse (menge_retourniert) -- Packplatz-Retoure und ERP-Gutschrift gar nicht,
--    dadurch waren Doppel-Gutschriften derselben Menge möglich.
--    Bestehende Kassen-Retouren waren immer auch erstattet -> Startwert = retourniert.
ALTER TABLE auftrag_positionen
    ADD COLUMN menge_gutgeschrieben INT UNSIGNED NOT NULL DEFAULT 0 AFTER menge_retourniert;
UPDATE auftrag_positionen SET menge_gutgeschrieben = menge_retourniert;

-- 2) Charge-Liste am Auftrag ("A123, B456, ...") wurde bei 20 Zeichen abgeschnitten.
ALTER TABLE auftrag_positionen
    MODIFY COLUMN charge VARCHAR(255) NULL;

-- 3) Rücklagerungen können jetzt auch aus einer ERP-Gutschrift kommen (nicht nur
--    von der Kasse) + merken, in welchen Artikel eingebucht wurde (Zustandsartikel).
ALTER TABLE packplatz_ruecklagerungen
    MODIFY COLUMN kassen_bon_id INT UNSIGNED NULL,
    MODIFY COLUMN bon_nr VARCHAR(30) NULL,
    MODIFY COLUMN kasse_id INT UNSIGNED NULL,
    ADD COLUMN quelle ENUM('kasse','gutschrift') NOT NULL DEFAULT 'kasse' AFTER id,
    ADD COLUMN gutschrift_nr VARCHAR(30) NULL AFTER bon_nr,
    ADD COLUMN auftrag_position_id INT UNSIGNED NULL AFTER auftrag_nr,
    ADD COLUMN lager_vorschlag_id INT UNSIGNED NULL AFTER charge,
    ADD COLUMN erledigt_artikel_id INT UNSIGNED NULL AFTER erledigt_zustand,
    MODIFY COLUMN erledigt_zustand ENUM('neu','gebraucht','retour','beschaedigt','defekt') NULL;
