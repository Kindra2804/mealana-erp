CREATE TABLE `versandklassen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NULL,
  `kuerzel` varchar(10) NULL,
  `sortierung` int unsigned DEFAULT 0,
  PRIMARY KEY (`id`)
);

ALTER TABLE `artikel`
    ADD COLUMN `laenge` INT UNSIGNED NULL AFTER `inhalt_einheit`,
    ADD COLUMN `breite` INT UNSIGNED NULL AFTER `laenge`,
    ADD COLUMN `hoehe` INT UNSIGNED NULL AFTER `breite`,
    ADD COLUMN `versandklasse_id` int UNSIGNED NULL AFTER `gewicht_versand`,
    ADD CONSTRAINT `fk_artikel_versandklasse`
    FOREIGN KEY (`versandklasse_id`) REFERENCES `versandklassen` (`id`) ON UPDATE CASCADE;
    