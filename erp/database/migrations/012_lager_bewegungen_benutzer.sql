ALTER TABLE `lager_bewegungen`
    ADD COLUMN `benutzer_id` INT UNSIGNED NULL,
    ADD CONSTRAINT `fk_lbew_benutzerId`
    FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE;
