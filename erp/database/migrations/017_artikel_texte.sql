ALTER TABLE artikel
    CHANGE COLUMN `beschreibung_kurz` `kurzbeschreibung` TEXT NULL,
    CHANGE COLUMN `beschreibung_lang` `beschreibung` LONGTEXT NULL,
    ADD COLUMN `technische_details` TEXT NULL,
    ADD COLUMN `beschreibung_intern` TEXT NULL,
    ADD COLUMN `meta_titel` VARCHAR(70) NULL,
    ADD COLUMN `meta_description` VARCHAR(160) NULL,
    ADD COLUMN `url_slug` VARCHAR(255) NULL;

ALTER TABLE artikel
    ADD UNIQUE KEY `uk_artikel_url_slug` (`url_slug`);
