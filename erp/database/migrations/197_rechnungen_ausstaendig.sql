-- Teilrechnung: "Noch ausständig"-Liste zum Zeitpunkt der Rechnung einfrieren, damit ein
-- späterer Nachdruck (mit aktueller Zahlungsinfo) nicht plötzlich den heutigen Stand zeigt.
ALTER TABLE rechnungen
    ADD COLUMN ausstaendig_json TEXT NULL AFTER dateiname;
