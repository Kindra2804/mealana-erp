-- Korrektur zu Migration 167: Mindestabnahme/Abnahmeintervall werden im Artikel-Formular in der
-- physischen Inhalt-Einheit des Artikels eingegeben (analog zur Grundpreis-Bezugsmenge). Fuer
-- METERWARE ist diese Einheit seit der Grundpreis-Rechtslage-Korrektur (siehe
-- project_grundpreis_rechtslage_wolle.md, 2026-08-13) 'm', nicht 'cm' -- der urspruengliche
-- Seed-Wert 20/10 (gedacht als "20cm/10cm" nach Jackys Vlieseline-Beispiel) war deshalb um den
-- Faktor 100 zu gross. Korrigiert auf 0,2/0,1 (= weiterhin 20cm/10cm, nur in Metern ausgedrueckt).
UPDATE artikel_typen SET mindestabnahme_default = 0.2, abnahmeintervall_default = 0.1 WHERE code = 'METERWARE';
