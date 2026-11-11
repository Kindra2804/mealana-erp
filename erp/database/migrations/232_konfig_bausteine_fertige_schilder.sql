-- 232: Schild-Bausteine + fertige Schilder (Jacky 2026-11-11, Plan vom 09.11.)
-- 1) konfig_bausteine: Kürzel + Rohmaterial ZENTRAL je Achse + Wert-Text. Jedes Layout hat eigene Kopien
--    der Werte (varianten_achse_werte), die Pflege gilt so für alle Layouts gleichzeitig.
--    Kürzel -> Artikelnummer fertiger Schilder; Rohmaterial -> Verfügbarkeit (Bestand 0 = im Shop
--    ausgegraut, an der Kasse Hinweis). varianten_achse_werte.rohmaterial_artikel_id (Migr. 169) bleibt
--    als Ausnahme je Layout und hat Vorrang.
-- 2) konfig_fertige_artikel: welches fertige Schild aus welchem Layout mit welchen Werten entstand.
--    Gleiche Kombination = gleiche Artikelnummer = derselbe Artikel (wird nur hochgezählt).
-- 3) Einstellung konfig_fertig_kategorie_id: Zielkategorie für fertige Schilder ("Fertige Schilder").
-- Wiederholbar (IF NOT EXISTS / INSERT IGNORE).

CREATE TABLE IF NOT EXISTS konfig_bausteine (
    achse_id               INT UNSIGNED NOT NULL,
    wert                   VARCHAR(100) NOT NULL,           -- Wert-Text wie in varianten_achse_werte.wert
    kuerzel                VARCHAR(12)  NULL DEFAULT NULL,  -- leer = kommt nicht in die Artikelnummer
    rohmaterial_artikel_id INT UNSIGNED NULL DEFAULT NULL,
    geaendert_am           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (achse_id, wert),
    KEY idx_kb_rohmaterial (rohmaterial_artikel_id),
    CONSTRAINT fk_kb_achse FOREIGN KEY (achse_id) REFERENCES varianten_achsen (id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_rohmaterial FOREIGN KEY (rohmaterial_artikel_id) REFERENCES artikel (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS konfig_fertige_artikel (
    artikel_id        INT UNSIGNED NOT NULL,
    layout_artikel_id INT UNSIGNED NOT NULL,
    werte_json        TEXT NOT NULL,     -- [{"achse_id":..,"achse":"..","wert_id":..,"wert":".."}, ...]
    erstellt_am       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (artikel_id),
    KEY idx_kfa_layout (layout_artikel_id),
    CONSTRAINT fk_kfa_artikel FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON DELETE CASCADE,
    CONSTRAINT fk_kfa_layout FOREIGN KEY (layout_artikel_id) REFERENCES artikel (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO system_einstellungen (schluessel, wert) VALUES ('konfig_fertig_kategorie_id', '');
