-- Chargen-Unterstützung für den Messe-Sync-Workflow: eine Umbuchungszeile
-- pro Artikel+Charge statt nur pro Artikel, damit Rückkehr/Post-Sync die
-- korrekten Chargen zurückbuchen können (kritisch für Lagerstand-Korrektheit).
ALTER TABLE kassen_messe_umbuchungen
    ADD COLUMN charge VARCHAR(100) NULL AFTER ean;
