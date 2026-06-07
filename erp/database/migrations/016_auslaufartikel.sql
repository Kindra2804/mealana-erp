ALTER TABLE artikel
    ADD COLUMN `ist_auslaufartikel` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE artikel_varianten
    ADD COLUMN `ist_auslaufartikel` TINYINT(1) NOT NULL DEFAULT 0;

INSERT INTO `benutzer`(`username`, `passwort`, `vorname`, `nachname`, `formularname`,  `aktiv`) 
    VALUES ('Jarvis','!','Jarvis','Worker','Jarvis', 0);
