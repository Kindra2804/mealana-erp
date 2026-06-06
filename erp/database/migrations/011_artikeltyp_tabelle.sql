-- Migration 011: artikeltyp ENUM → Tabelle artikel_typen
-- Schritt 1: Neue Tabelle anlegen
CREATE TABLE `artikel_typen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `hat_varianten` tinyint(1) NOT NULL DEFAULT 1,
  `hat_lagerstand` tinyint(1) NOT NULL DEFAULT 1,
  `ist_download` tinyint(1) NOT NULL DEFAULT 0,
  `ist_set` tinyint(1) NOT NULL DEFAULT 0,
  `sortierung` int(10) unsigned NOT NULL DEFAULT 0,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Schritt 2: Stammdaten einfügen
INSERT INTO `artikel_typen` (`code`, `name`, `hat_varianten`, `hat_lagerstand`, `ist_download`, `ist_set`, `sortierung`) VALUES
('GARN',      'Garn',      1, 1, 0, 0, 1),
('NADEL',     'Nadel',     1, 1, 0, 0, 2),
('METERWARE', 'Meterware', 1, 1, 0, 0, 3),
('DOWNLOAD',  'Download',  0, 0, 1, 0, 4),
('SET',       'Set',       0, 1, 0, 1, 5),
('STANDARD',  'Standard',  1, 1, 0, 0, 6);

-- Schritt 3: Neue Spalte hinzufügen (noch nullable für die Datenmigration)
ALTER TABLE `artikel`
    ADD COLUMN `artikeltyp_id` int(10) unsigned NULL AFTER `steuerklasse_id`;

-- Schritt 4: Bestehende Daten migrieren
UPDATE `artikel` a
JOIN `artikel_typen` at ON at.code = a.artikeltyp
SET a.artikeltyp_id = at.id;

-- Schritt 5: NOT NULL setzen und FK hinzufügen
ALTER TABLE `artikel`
    MODIFY COLUMN `artikeltyp_id` int(10) unsigned NOT NULL;

ALTER TABLE `artikel`
    ADD CONSTRAINT `fk_artikel_artikeltyp`
    FOREIGN KEY (`artikeltyp_id`) REFERENCES `artikel_typen` (`id`) ON UPDATE CASCADE;

-- Schritt 6: Alte ENUM-Spalte entfernen
ALTER TABLE `artikel` DROP COLUMN `artikeltyp`;
