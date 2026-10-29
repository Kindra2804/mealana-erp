-- Migration 213: Z-Bon deckt einen Zeitraum ab, nicht mehr den ganzen Tag (Jacky 2026-10-29)
--
-- Kommt nach dem Z-Bon am selben Tag noch ein Kunde, gibt es einen zweiten Z-Bon für diesen Tag —
-- er enthält nur die Buchungen seit dem vorigen Z-Bon. bis_zeitpunkt = Ende des abgedeckten
-- Zeitraums (exklusiv): Erstellungszeitpunkt, bei nachträglichen Z-Bons das Tagesende.
-- Grundlage für KassenService::zBereich(), zReihenfolgeFehler() und stornoSperrgrund():
-- Bons vor dem letzten bis_zeitpunkt stehen auf einem Z-Bon und sind nicht mehr stornierbar.

ALTER TABLE kassen_abschluesse
    ADD COLUMN bis_zeitpunkt DATETIME NULL AFTER datum;

UPDATE kassen_abschluesse
SET bis_zeitpunkt = LEAST(erstellt_am, DATE_ADD(datum, INTERVAL 1 DAY));
