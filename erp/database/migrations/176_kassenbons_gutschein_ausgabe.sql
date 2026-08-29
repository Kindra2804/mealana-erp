-- Gutschein-Modul, Baustufe 2: Kassen-Anbindung Retoure -> Gutschein statt Bar.
-- Eigener Zahlungsart-Wert (statt den bestehenden 'gutschein' wiederzuverwenden,
-- der schon "mit Gutschein BEZAHLEN" bedeutet -- eine andere Kasse-Funktion,
-- siehe project_gutscheine.md) fuer den Fall "Gutschein bei Retoure AUSSTELLEN".
ALTER TABLE kassen_bons
    MODIFY COLUMN zahlungsart ENUM('bar','karte_extern','gutschein','gutschein_ausgabe','kombi') NOT NULL DEFAULT 'bar';
