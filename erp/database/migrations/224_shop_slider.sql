-- 224: Shop-Slider (Jacky 2026-11-05)
-- Slides für den Hero-Slider der Startseite und für Banner auf Kategorieseiten, je Shop im ERP gepflegt.
-- Das ERP lädt die Bilder in die WP-Mediathek und schickt die Slide-Liste an das WP-Plugin „NAHTLOS Connector“.
-- Das Plugin blendet nach Datum selbst ein/aus (ERP ist nur per VPN erreichbar), Aktions-Slides bekommen
-- beim Übertragen den Zeitraum der Aktion als von/bis mit.

CREATE TABLE shop_slides (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shop_id INT UNSIGNED NOT NULL,
    -- startseite = Hero-Slider, kategorie = Banner oben auf der Kategorieseite (kategorie_id)
    position ENUM('startseite','kategorie') NOT NULL DEFAULT 'startseite',
    kategorie_id INT UNSIGNED NULL DEFAULT NULL,
    -- gestaltet = Bild + Text/Buttons live darüber; fertiges_bild = Bild enthält schon alles, ganze Slide ist ein Link (button1)
    art ENUM('gestaltet','fertiges_bild') NOT NULL DEFAULT 'gestaltet',
    bezeichnung VARCHAR(100) NOT NULL,                -- interner Name für die Liste (bei fertigem Bild ohne Titel)
    kicker VARCHAR(80) NULL DEFAULT NULL,             -- kleiner Titel über der Überschrift
    titel VARCHAR(150) NULL DEFAULT NULL,
    text VARCHAR(400) NULL DEFAULT NULL,
    button1_text VARCHAR(60) NULL DEFAULT NULL,
    button1_ziel_typ ENUM('kategorie','artikel','hersteller','url') NULL DEFAULT NULL,
    button1_ziel_id INT UNSIGNED NULL DEFAULT NULL,   -- kategorien.id / artikel.id / hersteller.id
    button1_url VARCHAR(255) NULL DEFAULT NULL,       -- nur bei ziel_typ = url
    button2_text VARCHAR(60) NULL DEFAULT NULL,
    button2_ziel_typ ENUM('kategorie','artikel','hersteller','url') NULL DEFAULT NULL,
    button2_ziel_id INT UNSIGNED NULL DEFAULT NULL,
    button2_url VARCHAR(255) NULL DEFAULT NULL,
    text_position ENUM('links','mitte','rechts') NOT NULL DEFAULT 'links',
    schrift ENUM('dunkel','hell') NOT NULL DEFAULT 'dunkel',
    -- Bilder liegen unter public/uploads/slider/{id}/; *_wp_id/*_synced = Stand in der WP-Mediathek
    bild_desktop VARCHAR(255) NULL DEFAULT NULL,
    bild_mobil VARCHAR(255) NULL DEFAULT NULL,
    bild_desktop_wp_id INT UNSIGNED NULL DEFAULT NULL,
    bild_desktop_wp_url VARCHAR(500) NULL DEFAULT NULL,
    bild_desktop_synced VARCHAR(255) NULL DEFAULT NULL,
    bild_mobil_wp_id INT UNSIGNED NULL DEFAULT NULL,
    bild_mobil_wp_url VARCHAR(500) NULL DEFAULT NULL,
    bild_mobil_synced VARCHAR(255) NULL DEFAULT NULL,
    alt_text VARCHAR(150) NULL DEFAULT NULL,
    -- immer / zeitraum (gueltig_von..gueltig_bis) / aktion (solange die Preis-Aktion läuft)
    sichtbar ENUM('immer','zeitraum','aktion') NOT NULL DEFAULT 'immer',
    gueltig_von DATETIME NULL DEFAULT NULL,
    gueltig_bis DATETIME NULL DEFAULT NULL,
    aktion_id INT UNSIGNED NULL DEFAULT NULL,
    sortierung INT NOT NULL DEFAULT 0,
    ist_aktiv TINYINT(1) NOT NULL DEFAULT 1,          -- manuell ausgeschaltet = 0
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    geaendert_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_slides_shop_pos (shop_id, position, sortierung),
    CONSTRAINT fk_slides_shop FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE,
    CONSTRAINT fk_slides_kategorie FOREIGN KEY (kategorie_id) REFERENCES kategorien (id) ON DELETE CASCADE,
    CONSTRAINT fk_slides_aktion FOREIGN KEY (aktion_id) REFERENCES aktionen (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Slider-Einstellungen + Übertragungsstand je Shop
ALTER TABLE shops
    ADD COLUMN slider_intervall_sek TINYINT UNSIGNED NOT NULL DEFAULT 6 AFTER wurzel_kategorie_id,
    ADD COLUMN slider_uebertragen_am DATETIME NULL DEFAULT NULL AFTER slider_intervall_sek,
    ADD COLUMN slider_fehler VARCHAR(500) NULL DEFAULT NULL AFTER slider_uebertragen_am,
    ADD COLUMN slider_geaendert TINYINT(1) NOT NULL DEFAULT 0 AFTER slider_fehler;  -- 1 = Änderungen noch nicht übertragen

-- Recht: Slider pflegen (Babsi), Admin + Assistent + Manager bekommen es gleich
INSERT INTO berechtigungen (name, beschreibung, aktiv) VALUES ('slider.verwalten', 'shop-slider pflegen und übertragen', 1);
INSERT INTO rollen_berechtigungen (rolle_id, berechtigung_id)
SELECT r.id, b.id FROM rollen r JOIN berechtigungen b ON b.name = 'slider.verwalten'
WHERE r.name IN ('superadmin', 'admin', 'assistent', 'manager');
