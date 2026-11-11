-- 230: Kategorie-Zuordnung für den JTL-Artikelimport (Jacky 2026-11-11)
-- Der JTL-Export liefert je Artikel die JTL-Kategorien (Spalten KategorieIDs + Kategorien, paarweise).
-- Im Import wählt man je JTL-Kategorie selbst die passende ERP-Kategorie (keine automatische
-- Wurzelkategorie je Shop). Die Wahl wird hier gemerkt und beim nächsten Import (andere Shops,
-- Nachträge) vorausgefüllt. kategorie_id NULL = bewusst „nicht zuordnen“.
-- Wiederholbar (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS import_kategorie_zuordnung (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quelle        VARCHAR(20)  NOT NULL DEFAULT 'jtl',
    fremd_id      VARCHAR(50)  NOT NULL COMMENT 'Kategorie-ID im Quellsystem (JTL KategorieIDs)',
    fremd_name    VARCHAR(255) NOT NULL DEFAULT '',
    kategorie_id  INT UNSIGNED NULL DEFAULT NULL COMMENT 'ERP-Kategorie, NULL = nicht zuordnen',
    geaendert_am  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_quelle_fremd (quelle, fremd_id),
    KEY idx_kategorie (kategorie_id),
    CONSTRAINT fk_import_kat_zuord_kategorie FOREIGN KEY (kategorie_id) REFERENCES kategorien (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
