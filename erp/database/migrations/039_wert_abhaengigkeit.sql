-- Wert-Ebenen-Abhängigkeit für bedingte Achsen (z.B. DROPS Fabel: Farbe abhängig von Typ)
-- Jeder Achsen-Wert kann optional auf einen anderen Wert verweisen:
--   "Royalblau" (Farbe) bedingungs_wert_id = id von "Uni" (Typ)
-- Unterschied zu artikel_achsen.bedingungs_wert_id: dort bedingte die ganze ACHSE,
-- hier bedingt der einzelne WERT — ermöglicht gefilterte Dropdown-/Swatch-Anzeige im Shop
ALTER TABLE varianten_achse_werte
    ADD COLUMN bedingungs_wert_id INT UNSIGNED NULL AFTER wert_zusatz,
    ADD CONSTRAINT fk_varAchsWert_bedWert
        FOREIGN KEY (bedingungs_wert_id)
        REFERENCES varianten_achse_werte(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL;
