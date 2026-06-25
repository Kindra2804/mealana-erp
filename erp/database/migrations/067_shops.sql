-- Migration 067: shops-Tabelle + shop_id auf auftraege
-- Löst die offene shop_id-Referenz in kunden_shops + artikel_bilder_shops auf.
-- Jeder Shop hat eigenes Logo + optional "Ein Unternehmen der MEALANA KG"-Zeile.

CREATE TABLE shops (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(50)  NOT NULL UNIQUE,   -- 'mealana', 'bio-wolle', 'sockenwolle'
    name        VARCHAR(100) NOT NULL,           -- Anzeigename auf Dokumenten
    logo_pfad   VARCHAR(255) NULL,              -- relativ zu public/, z.B. 'img/logos/mealana.png'
    sub_marke   TINYINT(1)   NOT NULL DEFAULT 0, -- 1 = zeigt "Ein Unternehmen der MEALANA KG"
    wc_url      VARCHAR(255) NULL,              -- WooCommerce REST API URL (für späteren Sync)
    wc_key      VARCHAR(255) NULL,              -- WC Consumer Key (verschlüsselt speichern!)
    wc_secret   VARCHAR(255) NULL,              -- WC Consumer Secret
    ist_aktiv   TINYINT(1)   NOT NULL DEFAULT 1,
    erstellt_am DATETIME     NOT NULL DEFAULT NOW()
);

-- Standarddaten: die drei MeaLana-Kanäle
INSERT INTO shops (slug, name, logo_pfad, sub_marke, ist_aktiv) VALUES
    ('mealana',      'MEALANA KG',           'img/logos/mealana.png',     0, 1),
    ('bio-wolle',    'bio-wolle.at',          'img/logos/bio-wolle.png',   1, 1),
    ('sockenwolle',  'sockenwolle-online.at', 'img/logos/sockenwolle.png', 1, 1);

-- shop_id auf auftraege: NULL = manuell/kasse (= MeaLana, shop_id 1)
ALTER TABLE auftraege
    ADD COLUMN shop_id INT UNSIGNED NULL AFTER kanal,
    ADD CONSTRAINT fk_auftraege_shop FOREIGN KEY (shop_id) REFERENCES shops(id);

-- Bestehende manuelle Aufträge bekommen shop_id = 1 (MeaLana)
UPDATE auftraege SET shop_id = 1 WHERE kanal IN ('manuell', 'kasse');
