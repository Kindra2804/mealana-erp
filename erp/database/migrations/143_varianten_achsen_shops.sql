-- Online-Shop-Anbindung: Vater/Kind-Artikel (Variable Products/Variations)
-- Achsen werden als globale WooCommerce-Attribute gesynct, Achsenwerte als
-- deren Terms -- beide brauchen pro Shop eine externe ID, analog zum
-- bestehenden kategorie_shops-Muster.

CREATE TABLE varianten_achsen_shops (
    achse_id            INT UNSIGNED NOT NULL,
    shop_id              INT UNSIGNED NOT NULL,
    externe_attribut_id  VARCHAR(100) NULL,
    PRIMARY KEY (achse_id, shop_id),
    CONSTRAINT fk_achseshop_achse FOREIGN KEY (achse_id) REFERENCES varianten_achsen(id) ON DELETE CASCADE,
    CONSTRAINT fk_achseshop_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE varianten_achse_werte_shops (
    wert_id              INT UNSIGNED NOT NULL,
    shop_id              INT UNSIGNED NOT NULL,
    externe_term_id      VARCHAR(100) NULL,
    PRIMARY KEY (wert_id, shop_id),
    CONSTRAINT fk_wertshop_wert FOREIGN KEY (wert_id) REFERENCES varianten_achse_werte(id) ON DELETE CASCADE,
    CONSTRAINT fk_wertshop_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
