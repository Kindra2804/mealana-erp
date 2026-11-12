-- 235: Anhänger mit Freitext für Stücke, die nicht im ERP sind (Babsis Strickstücke, Jacky 2026-11-12)
--
-- Damit diese Anhänger immer gleich aussehen wie die aus Artikeldaten: gleiche Vorlage "anhaenger",
-- eigene kleine Stammdaten-Tabelle zum Wiederverwenden/Nachdrucken.
-- pflege: JSON-Liste der Pflegesymbol-Schlüssel (wie merkmal_werte.symbol, z.B. ["handwaesche","liegend"])
-- badge:  kurzer Text in einem Rahmen (z.B. "UNIKAT"), leer = keiner
-- aktiv=0: verkauft/weggelegt, wird in der Liste ausgeblendet

CREATE TABLE IF NOT EXISTS anhaenger_freitext (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kopfzeile    VARCHAR(60)  NOT NULL DEFAULT '',
    titel        VARCHAR(80)  NOT NULL,
    material     VARCHAR(120) NOT NULL DEFAULT '',
    groesse      VARCHAR(80)  NOT NULL DEFAULT '',
    zusatz       VARCHAR(160) NOT NULL DEFAULT '',
    pflege       JSON NULL,
    preis        DECIMAL(10,2) NULL,
    badge        VARCHAR(20)  NOT NULL DEFAULT '',
    aktiv        TINYINT(1)   NOT NULL DEFAULT 1,
    erstellt_am  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    geaendert_am DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
