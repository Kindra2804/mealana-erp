-- Direktpreis statt Aufpreis als Standard fuer neue Achsen-Preis-Zuweisungen (artikel_achsen).
-- Aufpreis ist der Ausnahmefall (Zuschlag relativ zum Vater-VK), Direktpreis (absoluter VK pro
-- Achse) ist der Normalfall bei JTL-importierten Vater/Kind-Artikeln mit bekannten Zielpreisen.
ALTER TABLE artikel_achsen
    MODIFY COLUMN preis_modus ENUM('aufpreis','direktpreis') NOT NULL DEFAULT 'direktpreis';
