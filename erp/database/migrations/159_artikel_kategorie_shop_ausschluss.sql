-- Ein Artikel kann in mehreren Kategorien stehen und in mehreren Shops aktiv
-- sein -- bisher gab es aber keine Möglichkeit, EINE bestimmte Kategorie-
-- Zuweisung für EINEN bestimmten Shop auszublenden. Beispiel Jacky
-- (2026-08-05): Artikel steht in "Wolle/Hersteller/Elisa" UND
-- "Wolle/Hersteller/Sonstige", soll aber in Kanal 1 nur unter Elisa und in
-- Kanal 2 nur unter Sonstige erscheinen. artikel_shops kennt nur "im Shop
-- aktiv oder nicht" (ganzer Artikel), kategorie_shops.ausgeschlossen nur
-- "Kategorie im Shop aktiv oder nicht" (alle Artikel) -- die Kombination aus
-- allen drei (Artikel x Kategorie x Shop) fehlte. Bewusst als schlanke
-- Ausnahme-Tabelle (nur die tatsächlich ausgeschlossenen Kombinationen),
-- nicht als volle Matrix -- der Normalfall (Artikel erscheint in jeder
-- seiner Kategorien, in jedem Shop, in dem er aktiv ist) bleibt ohne
-- zusätzliche Zeilen bestehen.
CREATE TABLE artikel_kategorie_shop_ausschluss (
    artikel_id   INT UNSIGNED NOT NULL,
    kategorie_id INT UNSIGNED NOT NULL,
    shop_id      INT UNSIGNED NOT NULL,
    PRIMARY KEY (artikel_id, kategorie_id, shop_id),
    CONSTRAINT fk_akshopaus_artikel   FOREIGN KEY (artikel_id)   REFERENCES artikel(id)    ON DELETE CASCADE,
    CONSTRAINT fk_akshopaus_kategorie FOREIGN KEY (kategorie_id) REFERENCES kategorien(id) ON DELETE CASCADE,
    CONSTRAINT fk_akshopaus_shop      FOREIGN KEY (shop_id)      REFERENCES shops(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
