CREATE TABLE `einheiten` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `kuerzel` varchar(10) NULL,
  `sortierung` int unsigned DEFAULT 0,
  PRIMARY KEY (`id`)
);

INSERT INTO `einheiten` (`name`, `kuerzel`) VALUES
('Knäuel', 'Kn'),
('Meter', 'm'),
('Gramm', 'g'),
('Stk', 'Stk'),
('Set', 'Set');

ALTER TABLE `artikel`
    ADD COLUMN `einheit_id` int(10) unsigned NULL AFTER `einheit`,
    ADD CONSTRAINT `fk_artikel_einheitId`
    FOREIGN KEY (`einheit_id`) REFERENCES `einheiten` (`id`) ON UPDATE CASCADE;

UPDATE `artikel` JOIN `einheiten` ON `einheiten`.`name` = `artikel`.`einheit` SET `artikel`.`einheit_id` = `einheiten`.`id`;

ALTER TABLE `artikel`
    MODIFY COLUMN `einheit_id` INT UNSIGNED NOT NULL,
    DROP COLUMN `einheit`;


