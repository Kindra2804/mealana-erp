-- 223: Wurzelkategorie je Shop (Jacky 2026-11-04)
-- Ein Sub-Marken-Shop (z.B. indra-design.at) zeigt nur einen Ast des ERP-Kategoriebaums.
-- Ist hier eine Kategorie eingetragen, wird sie selbst NICHT in den Shop übertragen;
-- ihre Unterkategorien werden dort zu Hauptkategorien (eine Ebene weniger in Menü und Breadcrumbs).
-- Im Hauptshop bleibt das Feld leer → voller Pfad wie bisher.

ALTER TABLE shops
    ADD COLUMN wurzel_kategorie_id INT UNSIGNED NULL DEFAULT NULL AFTER sub_marke,
    ADD CONSTRAINT fk_shops_wurzel_kategorie FOREIGN KEY (wurzel_kategorie_id) REFERENCES kategorien (id) ON DELETE SET NULL;
