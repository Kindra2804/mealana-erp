-- Abholfach (Jacky 2026-10-02): bei Abholungs-Aufträgen heißt menge_geliefert "gepackt / aus
-- dem Lager" (Packplatz). Neu: menge_abgeholt = aus dem Abholfach genommen — an der Kasse
-- übergeben ODER vom Kunden nicht gewollt (dann über die Packplatz-Rücklagerung zurück).
-- Im Abholfach liegt damit menge_geliefert − menge_abgeholt. "Rest holt der Kunde später"
-- lässt die Ware im Fach (keine Lagerbuchung), beim nächsten Laden wird sie übergeben.
ALTER TABLE auftrag_positionen
    ADD COLUMN menge_abgeholt INT UNSIGNED NOT NULL DEFAULT 0 AFTER menge_geliefert;

-- Altbestand: Abholungen, die schon (teilweise) an der Kasse waren. Bisher wurde nicht
-- Mitgenommenes direkt ins Lager zurückgebucht — es liegt also nichts mehr im Fach.
UPDATE auftrag_positionen p
JOIN auftraege a ON a.id = p.auftrag_id
SET p.menge_abgeholt = LEAST(p.menge, p.menge_geliefert + p.menge_retourniert)
WHERE a.lieferart = 'abholung' AND a.lieferstatus IN ('teilgeliefert', 'abgeschlossen');
