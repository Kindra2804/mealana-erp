ALTER TABLE `lager_bewegungen`
    ADD COLUMN `lieferant_id` int unsigned NULL AFTER `lager_id`,
    ADD CONSTRAINT `fk_lbew_lieferantId`
    FOREIGN KEY (`lieferant_id`) REFERENCES `lieferanten` (`id`) ON UPDATE CASCADE;