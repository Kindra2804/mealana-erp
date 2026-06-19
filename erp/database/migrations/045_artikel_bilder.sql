CREATE TABLE artikel_bilder (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artikel_id      INT UNSIGNED NOT NULL,
    dateiname       VARCHAR(255) NOT NULL,
    alt_text        VARCHAR(255) NOT NULL DEFAULT '',
    position        INT NOT NULL DEFAULT 0,
    erstellt_am     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_artbild_artikel FOREIGN KEY (artikel_id) REFERENCES artikel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE artikel_bilder_shops (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bild_id         INT UNSIGNED NOT NULL,
    shop_id         INT UNSIGNED NOT NULL,
    external_id     VARCHAR(255) NULL,
    sync_status     ENUM('pending','synced','error') NOT NULL DEFAULT 'pending',
    synced_at       TIMESTAMP NULL,
    fehler_meldung  TEXT NULL,
    CONSTRAINT fk_bildsync_bild FOREIGN KEY (bild_id) REFERENCES artikel_bilder(id) ON DELETE CASCADE,
    UNIQUE KEY uq_bild_shop (bild_id, shop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Uploads-Ordner wird per PHP erstellt (uploads/artikel/{id}/)
