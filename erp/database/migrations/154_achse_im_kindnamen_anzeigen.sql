-- Bisher wurde beim VarKombi-Generator immer der Achsenname vor den Wert gehängt
-- (z.B. "Farbe rot (18)"), was bei der reinen Farb-Achse unnötig/redundant ist,
-- bei Sub-Achsen wie [Uni]/[Mix] aber gebraucht wird. Jetzt pro Achse einstellbar
-- statt hart codiert, damit es auch für künftige Achsen (Stärke, Länge, ...) passt.
ALTER TABLE varianten_achsen
    ADD COLUMN im_kindnamen_anzeigen TINYINT(1) NOT NULL DEFAULT 1 AFTER ist_gruppe;

-- Bestehende Daten: "Farbe" selbst soll nicht mehr im Kindnamen auftauchen,
-- die Sub-Achsen [Uni]/[Mix] bleiben sichtbar (Default 1 passt für die schon).
UPDATE varianten_achsen SET im_kindnamen_anzeigen = 0 WHERE code = 'farbe' AND abhaengig_von_achse_id IS NULL;
