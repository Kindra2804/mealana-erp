-- 226: Shop-Menü aus dem ERP (Jacky 2026-11-05)
-- Hauptmenü je Shop im ERP pflegen (max. 3 Ebenen). Das WP-Plugin „NAHTLOS Connector“ baut daraus ein echtes
-- WordPress-Menü („NAHTLOS Hauptmenü“) und hängt es an den Theme-Ort (shops.menue_orte, Blocksy: menu_1).
-- Dynamisch im Shop (bei jedem Seitenaufruf): „Alle Hersteller“ (Hersteller mit Artikeln, in Spalten) und
-- „nur wenn Artikel drin“ (z. B. Sale). Unterkategorien automatisch löst das ERP beim Übertragen auf;
-- der Cron überträgt neu, wenn sich Kategorien geändert haben.

CREATE TABLE shop_menue_punkte (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shop_id INT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED NULL DEFAULT NULL,          -- übergeordneter Menüpunkt (max. 3 Ebenen)
    sortierung INT NOT NULL DEFAULT 0,
    -- kategorie / hersteller_alle (alle Hersteller mit Artikeln) / hersteller / artikel / url
    typ ENUM('kategorie','hersteller_alle','hersteller','artikel','url') NOT NULL,
    ziel_id INT UNSIGNED NULL DEFAULT NULL,            -- kategorien.id / hersteller.id / artikel.id
    url VARCHAR(255) NULL DEFAULT NULL,                -- nur bei typ = url
    bezeichnung VARCHAR(100) NULL DEFAULT NULL,        -- leer = Name des Ziels
    unterkategorien_auto TINYINT(1) NOT NULL DEFAULT 0, -- Kategorie: Unterkategorien automatisch (bis Ebene 3)
    nur_mit_artikeln TINYINT(1) NOT NULL DEFAULT 0,    -- im Shop ausblenden, solange keine Artikel drin sind
    spalten TINYINT(1) NOT NULL DEFAULT 1,             -- hersteller_alle: Untermenü in Spalten
    ist_aktiv TINYINT(1) NOT NULL DEFAULT 1,
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    geaendert_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_menue_shop (shop_id, parent_id, sortierung),
    CONSTRAINT fk_menue_shop FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE,
    CONSTRAINT fk_menue_parent FOREIGN KEY (parent_id) REFERENCES shop_menue_punkte (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE shops
    ADD COLUMN menue_orte VARCHAR(100) NOT NULL DEFAULT 'menu_1' AFTER slider_geaendert,   -- Theme-Menüorte, Komma-getrennt
    ADD COLUMN menue_uebertragen_am DATETIME NULL DEFAULT NULL AFTER menue_orte,
    ADD COLUMN menue_fehler VARCHAR(500) NULL DEFAULT NULL AFTER menue_uebertragen_am,
    ADD COLUMN menue_geaendert TINYINT(1) NOT NULL DEFAULT 0 AFTER menue_fehler;

-- Gleiches Recht wie der Slider (Babsi pflegt beides)
UPDATE berechtigungen SET beschreibung = 'shop-slider, kacheln und shop-menü pflegen und übertragen' WHERE name = 'slider.verwalten';
