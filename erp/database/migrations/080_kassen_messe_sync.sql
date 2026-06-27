-- Migration 080: Messe-Sync Tracking
-- Protokolliert Pre-Sync und Post-Sync Vorgänge der Offline-Kasse

CREATE TABLE kassen_messe_sync (
    id            INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kasse_id      INT(10) UNSIGNED NOT NULL,
    lager_id      INT(10) UNSIGNED NOT NULL,
    typ           ENUM('pre','post') NOT NULL,
    status        ENUM('vorbereitet','abgeschlossen','fehler') NOT NULL DEFAULT 'vorbereitet',
    artikel_count INT UNSIGNED NOT NULL DEFAULT 0,
    bon_count     INT UNSIGNED NOT NULL DEFAULT 0,
    umsatz        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    sync_token    VARCHAR(64) NOT NULL UNIQUE,
    notiz         VARCHAR(500) NULL,
    benutzer_id   INT(10) UNSIGNED NOT NULL,
    erstellt_am   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    abgeschlossen_am DATETIME NULL,
    FOREIGN KEY (kasse_id)    REFERENCES kassen(id),
    FOREIGN KEY (lager_id)    REFERENCES lager(id),
    FOREIGN KEY (benutzer_id) REFERENCES benutzer(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Messe-Umbuchungs-Log: welche Artikel wurden zur Messe mitgenommen
CREATE TABLE kassen_messe_umbuchungen (
    id          INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sync_id     INT(10) UNSIGNED NOT NULL,
    artikel_id  INT(10) UNSIGNED NOT NULL,
    bezeichnung VARCHAR(300) NOT NULL,
    ean         VARCHAR(50)  NULL,
    menge_raus  DECIMAL(10,3) NOT NULL,
    menge_rueck DECIMAL(10,3) NOT NULL DEFAULT 0,
    menge_verkauft DECIMAL(10,3) GENERATED ALWAYS AS (menge_raus - menge_rueck) STORED,
    menge_schwund  DECIMAL(10,3) NOT NULL DEFAULT 0,
    FOREIGN KEY (sync_id)    REFERENCES kassen_messe_sync(id),
    FOREIGN KEY (artikel_id) REFERENCES artikel(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
