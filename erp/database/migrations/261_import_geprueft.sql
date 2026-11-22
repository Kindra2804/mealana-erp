-- 261: Welche Artikel einer Import-Quelle schon jemand angeschaut hat (Babsi/Jacky 2026-11-22)
--
-- Babsis Auswahl (übernehmen / nicht übernehmen) lief über einen unvollständigen JTL-Export – 390 Väter und
-- 788 Einzelartikel hat sie nie gesehen. Ohne diese Tabelle wäre "nicht ausgeschlossen" = "übernehmen",
-- auch für Artikel, die niemand geprüft hat. Die Vorschau "JTL: neue Nummern" markiert alles, was hier
-- fehlt, als "noch nicht geprüft"; ein Klick auf das Häkchen oder "Seite als geprüft markieren" trägt ein.
-- Gegenstück zu import_ausschluss (Migr. 254, was weg soll) und import_umbenennung (Migr. 260). Wiederholbar.

CREATE TABLE IF NOT EXISTS import_geprueft (
    quelle      VARCHAR(30)  NOT NULL,
    nummer      VARCHAR(50)  NOT NULL,
    benutzer_id INT UNSIGNED NULL,
    geprueft_am TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (quelle, nummer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
