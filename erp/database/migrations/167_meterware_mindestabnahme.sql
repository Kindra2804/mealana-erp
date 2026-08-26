-- Mindestabnahme + Abnahmeintervall fuer teilbare Artikeltypen (aktuell nur METERWARE,
-- z.B. Vlieseline: 20cm Mindestabnahme, 10cm Intervall). Siehe project_meterware_mindestabnahme.md.
--
-- Vorgabe lebt am Artikeltyp (editierbar in Einstellungen -> Mindestabnahme), ein einzelner
-- Artikel kann per mindestabnahme_modus davon abweichen oder die Funktion ganz abschalten --
-- bewusst NICHT global fuer den ganzen Typ erzwungen (Checkbox/Auswahl pro Artikel).
ALTER TABLE artikel_typen
    ADD COLUMN mindestabnahme_default DECIMAL(10,3) NULL AFTER teilbar,
    ADD COLUMN abnahmeintervall_default DECIMAL(10,3) NULL AFTER mindestabnahme_default;

ALTER TABLE artikel
    ADD COLUMN mindestabnahme_modus ENUM('erbt_typ','eigene_werte','deaktiviert') NOT NULL DEFAULT 'erbt_typ' AFTER grundpreis_anzeigen,
    ADD COLUMN mindestabnahme DECIMAL(10,3) NULL AFTER mindestabnahme_modus,
    ADD COLUMN abnahmeintervall DECIMAL(10,3) NULL AFTER mindestabnahme;

-- Startwert aus Jackys Vlieseline-Beispiel -- ueber die neue Einstellungen-Seite anpassbar,
-- kein Code-Deploy noetig fuer spaetere Korrekturen.
UPDATE artikel_typen SET mindestabnahme_default = 20, abnahmeintervall_default = 10 WHERE code = 'METERWARE';
