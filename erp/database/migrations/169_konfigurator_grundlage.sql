-- Vorbereitung für Konfiguratorartikel
-- flag für kennzeichnung als konfigurierbarer Artikel in artikel
-- rohmaterial-Verknüpfung in varianten_achse_werte
ALTER TABLE artikel
ADD COLUMN ist_konfigurierbar TINYINT(1) NOT NULL DEFAULT 0 AFTER hat_eigenen_lagerstand;

ALTER TABLE varianten_achse_werte
ADD COLUMN rohmaterial_artikel_id INT UNSIGNED NULL,
ADD CONSTRAINT fk_varachswert_rohmaterial FOREIGN KEY (rohmaterial_artikel_id) REFERENCES artikel (id) ON UPDATE CASCADE ON DELETE SET NULL;