-- Kassen-Retouren, die zusammen mit Extras in einem Bon zu einem Web-Auftrag landen,
-- werden im internen K1-Auftrag-Spiegel (siehe bon_speichern.php, K1-Split-Logik) als
-- eigene Positionen mit NEGATIVER Menge geführt (Retour-Anteil). Die Spalte war bisher
-- UNSIGNED und hätte das je nach SQL-Modus stillschweigend abgeschnitten oder einen
-- Fehler geworfen. Betrifft ausschließlich diese kasseninternen K1-Aufträge — normale
-- Web-Aufträge haben nie eine negative Menge.
ALTER TABLE auftrag_positionen
    MODIFY COLUMN menge INT NOT NULL;
