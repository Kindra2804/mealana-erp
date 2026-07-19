-- Online-Shop-Anbindung Phase 1: Grundgerüst für Artikel/Kategorien-Sync
-- Richtung ERP -> Shop (WooCommerce), Datenmodell analog zu den bereits
-- bestehenden Sync-Tabellen kunden_shops/artikel_bilder_shops.

-- Fehlte bisher komplett: ohne Änderungs-Zeitstempel kann kein Sync-Cron
-- erkennen, welche Artikel sich seit dem letzten Abgleich geändert haben.
ALTER TABLE artikel
    ADD COLUMN aktualisiert_am TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP AFTER erstellt_am;

CREATE TABLE artikel_shops (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artikel_id     INT UNSIGNED NOT NULL,
    shop_id        INT UNSIGNED NOT NULL,
    -- Kanal-Chip im Artikel-Formular: 0 = beim nächsten Sync im Shop auf
    -- "Entwurf" setzen, nicht löschen (SEO/Bestellhistorie bleibt erhalten).
    aktiv          TINYINT(1) NOT NULL DEFAULT 1,
    -- WooCommerce Produkt-ID (Vater/Standard) bzw. Variation-ID (Kind-Artikel).
    external_id    VARCHAR(255) NULL,
    sync_status    ENUM('pending','synced','error') NOT NULL DEFAULT 'pending',
    synced_at      TIMESTAMP NULL,
    fehler_meldung TEXT NULL,
    UNIQUE KEY uq_artikel_shop (artikel_id, shop_id),
    KEY idx_artshop_sync_status (sync_status),
    CONSTRAINT fk_artshop_artikel FOREIGN KEY (artikel_id) REFERENCES artikel(id) ON DELETE CASCADE,
    CONSTRAINT fk_artshop_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE kategorie_shops (
    kategorie_id         INT UNSIGNED NOT NULL,
    shop_id              INT UNSIGNED NOT NULL,
    externe_kategorie_id VARCHAR(100) NULL,
    PRIMARY KEY (kategorie_id, shop_id),
    CONSTRAINT fk_katshop_kategorie FOREIGN KEY (kategorie_id) REFERENCES kategorien(id) ON DELETE CASCADE,
    CONSTRAINT fk_katshop_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
