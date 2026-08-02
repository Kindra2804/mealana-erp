-- Ein Artikel kann in mehreren Kategorien stehen, auch in Kategorien die NICHT
-- im Shop erscheinen sollen (z.B. rein interne Sortierhilfen). Bisher wurde
-- JEDE Kategorie eines Artikels ungefiltert in den Shop gepusht (siehe
-- ShopSyncRepository::findKategorieIdsFuerArtikel/findWcKategorieIds) --
-- es gab keine Möglichkeit, eine Kategorie gezielt für einen bestimmten Shop
-- auszuschließen. Pro Kategorie UND pro Shop einzeln einstellbar (nicht
-- global), da mehrere echte Shops (MEALANA/bio-wolle/sockenwolle) existieren
-- und eine Kategorie je nach Shop unterschiedlich passend sein kann.
ALTER TABLE kategorie_shops
    ADD COLUMN ausgeschlossen TINYINT(1) NOT NULL DEFAULT 0 AFTER shop_id;
