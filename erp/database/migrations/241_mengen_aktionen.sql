-- 241: Mengen-Aktionen "X Stück für Y €" und "Kauf X, zahl Y" (Jacky 2026-11-14)
-- Erster Fall: Indra-Weihnachtsanhänger 3,50 €/Stk, 4 für 12 € gemischt (bisher WPCode-Snippet 34685 + Kasse händisch).
-- Eigene Tabellen, NICHT das bestehende Aktionsmodul (das setzt feste Preise je Kategorie).
-- Regeln (Rechenkern kommt in Schritt 2):
--   - Artikel einer Aktion = Artikel der Kategorien (inkl. Unterkategorien) + einzeln zugeordnete; Vater nimmt Kinder mit
--   - wiederholt sich (9 Stk bei "4 für 12" = 2 Pakete + 1 normal)
--   - x_fuer_y: teuerste Stücke zuerst ins Paket; kauf_x_zahl_y: günstigstes Stück gratis
--   - Rabatt wird GLEICHMÄSSIG auf alle Stücke im Paket verteilt (Teil-Retoure bleibt fair)
--   - nur Standard-Kundengruppe; Artikel mit laufendem Sale-/Aktionspreis zählen nicht mit
-- Wiederholbar (IF NOT EXISTS)

CREATE TABLE IF NOT EXISTS mengen_aktionen (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    art           ENUM('x_fuer_y', 'kauf_x_zahl_y') NOT NULL,
    menge         SMALLINT UNSIGNED NOT NULL,           -- X: Stück je Paket (4 für 12 → 4, 3 für 2 → 3)
    zahl_menge    SMALLINT UNSIGNED NULL DEFAULT NULL,  -- nur kauf_x_zahl_y: bezahlte Stück (3 für 2 → 2)
    paketpreis    DECIMAL(8,2) NULL DEFAULT NULL,       -- nur x_fuer_y: Preis je Paket brutto (4 für 12 → 12.00)
    badge_text    VARCHAR(40) NOT NULL,                 -- Badge im Shop / Hinweis auf dem Beleg, z.B. "4 für 12 €"
    gueltig_ab    DATE NOT NULL,
    gueltig_bis   DATE NULL DEFAULT NULL,               -- NULL = ohne Ende
    aktiv         TINYINT(1) NOT NULL DEFAULT 1,        -- Schalter zum Pausieren
    an_kasse      TINYINT(1) NOT NULL DEFAULT 1,        -- gilt an den Kassen
    erstellt_am   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aktualisiert_am TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Kategorien der Aktion (Unterkategorien zählen mit)
CREATE TABLE IF NOT EXISTS mengen_aktion_kategorien (
    aktion_id     INT UNSIGNED NOT NULL,
    kategorie_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (aktion_id, kategorie_id),
    FOREIGN KEY (aktion_id) REFERENCES mengen_aktionen(id) ON DELETE CASCADE,
    FOREIGN KEY (kategorie_id) REFERENCES kategorien(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Einzeln zugeordnete Artikel (Massenaktion in der Artikelliste); ein Vater nimmt seine Kinder mit
CREATE TABLE IF NOT EXISTS mengen_aktion_artikel (
    aktion_id     INT UNSIGNED NOT NULL,
    artikel_id    INT UNSIGNED NOT NULL,
    PRIMARY KEY (aktion_id, artikel_id),
    FOREIGN KEY (aktion_id) REFERENCES mengen_aktionen(id) ON DELETE CASCADE,
    FOREIGN KEY (artikel_id) REFERENCES artikel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Shops, in denen die Aktion gilt (Kasse: Spalte an_kasse)
CREATE TABLE IF NOT EXISTS mengen_aktion_shops (
    aktion_id     INT UNSIGNED NOT NULL,
    shop_id       INT UNSIGNED NOT NULL,
    PRIMARY KEY (aktion_id, shop_id),
    FOREIGN KEY (aktion_id) REFERENCES mengen_aktionen(id) ON DELETE CASCADE,
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
