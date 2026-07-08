-- Separates Tracking, wie viel einer Auftrags-Position bereits über die Kasse
-- retourniert wurde -- bewusst NICHT mit menge_geliefert vermischt, da das weiterhin
-- rein den Liefer-/Abholfortschritt bedeutet (auch bei teilgeliefert-Aufträgen relevant,
-- unabhängig von Retouren). Wird für die Obergrenze bei "Gutschrift erstellen" gebraucht,
-- damit kein bereits über die Kasse erstatteter Teil ein zweites Mal gutgeschrieben werden kann.
ALTER TABLE auftrag_positionen
    ADD COLUMN menge_retourniert INT UNSIGNED NOT NULL DEFAULT 0 AFTER menge_geliefert;
