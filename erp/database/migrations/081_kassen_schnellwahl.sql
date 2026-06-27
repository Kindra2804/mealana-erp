-- Migration 081: Schnellwahl-Tasten für Kassenschirm
-- 9 konfigurierbare Slots pro Kasse, Admin-verwaltbar

CREATE TABLE kassen_schnellwahl (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kasse_id   INT UNSIGNED NOT NULL,
    slot       TINYINT UNSIGNED NOT NULL COMMENT '1-9, links oben nach rechts unten',
    artikel_id INT UNSIGNED NULL,
    label      VARCHAR(100) NULL COMMENT 'Überschreibt Artikelname wenn gesetzt',
    UNIQUE KEY uk_kasse_slot (kasse_id, slot),
    FOREIGN KEY (kasse_id)   REFERENCES kassen(id)   ON DELETE CASCADE,
    FOREIGN KEY (artikel_id) REFERENCES artikel(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
