-- Aufpreis / Direktpreis pro Achse-Zuweisung (artikel_achsen)
-- preis_modus: 'aufpreis' = addiert auf Vater-VK, 'direktpreis' = absoluter VK für alle Kinder dieser Achse
-- preis_wert:  0 = kein Effekt
ALTER TABLE artikel_achsen
    ADD COLUMN preis_modus  ENUM('aufpreis','direktpreis') NOT NULL DEFAULT 'aufpreis',
    ADD COLUMN preis_wert   DECIMAL(10,2)                  NOT NULL DEFAULT 0.00;
