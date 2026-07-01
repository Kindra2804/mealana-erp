-- Migration 094: Ausgabeformat pro Kasse konfigurierbar
-- fragen = nach jeder Zahlung Auswahl; 80mm = immer Thermodruck; a4 = immer A4-PDF

ALTER TABLE kassen
    ADD COLUMN ausgabe_format ENUM('fragen','80mm','a4') NOT NULL DEFAULT 'fragen'
        COMMENT 'Bon-Ausgabeformat: fragen=Auswahl nach Zahlung, 80mm=Thermodruck, a4=A4-Rechnung'
    AFTER modus;
