-- 234: Preisschilder -- Protokoll jedes gedruckten Schilds (Jacky 2026-11-12)
--
-- Wofür: Liste "Schilder veraltet" (Preis/Aktion/Badges haben sich seit dem letzten Druck geändert)
-- und Nachweis, was wann ausgezeichnet wurde. Pro Druck und Artikel eine Zeile, die jüngste je
-- (artikel_id, vorlage) zählt.
--
-- vorlage:   messe | laden | aufsteller | ivar | anhaenger | kuvert
-- preis:     gedruckter Preis (brutto), statt_preis: durchgestrichener Preis (NULL = keiner)
-- merkmale:  Kurzfassung der gedruckten Badges/Texte als JSON, um Änderungen zu erkennen

CREATE TABLE IF NOT EXISTS preisschild_drucke (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    artikel_id   INT UNSIGNED NOT NULL,
    vorlage      VARCHAR(20)  NOT NULL,
    preis        DECIMAL(10,2) NULL,
    statt_preis  DECIMAL(10,2) NULL,
    merkmale     JSON NULL,
    benutzer_id  INT UNSIGNED NULL,
    gedruckt_am  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_psd_artikel_vorlage (artikel_id, vorlage, gedruckt_am),
    CONSTRAINT fk_psd_artikel FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
