-- Online-Shop-Anbindung: Hersteller-Filter als WooCommerce-Produktattribut
-- (Entscheidung 2026-07-20, siehe project_hersteller_shop_filter.md) --
-- unabhaengig vom bestehenden Hersteller-Kategorie-Ast, der als reine
-- Vor-Filter-Gruppierung fuer Kunden bestehen bleibt.
--
-- Nur eine Zuordnungstabelle noetig (keine achsen_shops-Entsprechung fuer
-- das Attribut selbst): "Hersteller" ist EIN globales WC-Attribut, das beim
-- Sync per Name nachgeschlagen/angelegt wird (wie varianten_achsen_shops,
-- nur ohne eigene Tabelle fuers Attribut -- der Lookup ist billig genug,
-- um ihn nicht extra zu cachen). Jeder einzelne Hersteller (DROPS, Regia..)
-- wird ein Term darunter, das braucht die externe Term-ID pro Shop.
CREATE TABLE hersteller_shops (
    hersteller_id    INT UNSIGNED NOT NULL,
    shop_id          INT UNSIGNED NOT NULL,
    externe_term_id  VARCHAR(100) NULL,
    PRIMARY KEY (hersteller_id, shop_id),
    CONSTRAINT fk_herstellershop_hersteller FOREIGN KEY (hersteller_id) REFERENCES hersteller(id) ON DELETE CASCADE,
    CONSTRAINT fk_herstellershop_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
