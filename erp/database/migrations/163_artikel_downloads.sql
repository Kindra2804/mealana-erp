ALTER TABLE artikel
    ADD COLUMN download_dateiname VARCHAR(255) NULL AFTER ist_hervorgehoben,
    ADD COLUMN download_limit     INT NULL         AFTER download_dateiname;

CREATE TABLE artikel_downloads_shops (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artikel_id          INT UNSIGNED NOT NULL,
    shop_id             INT UNSIGNED NOT NULL,
    dateiname_synced    VARCHAR(255) NULL,
    external_media_id   VARCHAR(255) NULL,
    external_media_url  VARCHAR(500) NULL,
    sync_status         ENUM('pending','synced','error') NOT NULL DEFAULT 'pending',
    synced_at           TIMESTAMP NULL,
    fehler_meldung      TEXT NULL,
    CONSTRAINT fk_downloadshop_artikel FOREIGN KEY (artikel_id) REFERENCES artikel(id) ON DELETE CASCADE,
    UNIQUE KEY uq_download_shop (artikel_id, shop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Uploads-Ordner wird per PHP erstellt (uploads/downloads/{artikel_id}/), analog uploads/artikel/
