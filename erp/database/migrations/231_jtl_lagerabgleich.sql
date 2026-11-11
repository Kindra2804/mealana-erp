-- 231: JTL-Lagerabgleich für den Parallelbetrieb (Jacky/Babsi 2026-11-11)
-- MeaLana-Shop + Laden laufen vorerst weiter auf JTL, SOW auf dem ERP. Täglicher Abgleich:
--   1. ERP -> JTL: Export = ERP-Bestand jetzt minus Basis (= was JTL beim letzten Abgleich hatte),
--      je Artikel/Lager/Charge. Einspielen per Ameise "Preise/Bestände (QuickSync)" mit
--      "Neuen und alten Lagerbestand addieren". Danach im ERP bestätigen.
--   2. JTL -> ERP: eigener JTL-Export (alle Artikel, auch Bestand 0) hochladen. Ziel je Schlüssel =
--      JTL + (ERP jetzt - Export-Schnappschuss), Differenz als Lagerbewegung "korrektur".
--      Neue Basis = JTL-Werte.
-- Sperre: Schritt 2 nur, wenn der letzte Export bestätigt ist (oder es seit der Basis keine Änderung gab).
-- Wiederholbar (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS jtl_abgleich_laeufe (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- export_offen: Datei erstellt, in JTL noch nicht bestätigt; export_bestaetigt: bereit für den Import;
    -- abgeschlossen: JTL-Import gebucht (liefert die Basis); verworfen: Export nicht eingespielt
    status               ENUM('export_offen','export_bestaetigt','abgeschlossen','verworfen') NOT NULL DEFAULT 'export_offen',
    ist_erstabgleich     TINYINT(1) NOT NULL DEFAULT 0,
    export_am            DATETIME NULL DEFAULT NULL,
    export_zeilen        INT UNSIGNED NOT NULL DEFAULT 0,
    export_hinweise      TEXT NULL DEFAULT NULL,
    export_bestaetigt_am DATETIME NULL DEFAULT NULL,
    import_am            DATETIME NULL DEFAULT NULL,
    import_datei         VARCHAR(255) NULL DEFAULT NULL,
    import_ergebnis      TEXT NULL DEFAULT NULL,      -- JSON: Zähler + Hinweise
    benutzer_id          INT UNSIGNED NULL DEFAULT NULL,
    erstellt_am          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_jal_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Schnappschüsse je Lauf: 'export' = ERP-Bestand beim Export, 'basis' = JTL-Bestand beim Import.
-- charge '' = ohne Charge (lagerbestand.charge IS NULL). Nur Zeilen mit Bestand <> 0.
CREATE TABLE IF NOT EXISTS jtl_abgleich_bestand (
    lauf_id    INT UNSIGNED NOT NULL,
    art        ENUM('export','basis') NOT NULL,
    artikel_id INT UNSIGNED NOT NULL,
    lager_id   INT UNSIGNED NOT NULL,
    charge     VARCHAR(20) NOT NULL DEFAULT '',
    bestand    DECIMAL(10,3) NOT NULL,
    PRIMARY KEY (lauf_id, art, artikel_id, lager_id, charge),
    KEY idx_jab_artikel (artikel_id),
    CONSTRAINT fk_jab_lauf FOREIGN KEY (lauf_id) REFERENCES jtl_abgleich_laeufe (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Welche ERP-Artikel gehören zum Abgleich (= standen in der letzten JTL-Datei, auch mit Bestand 0).
-- Artikel, die es nur im ERP gibt (z.B. indra-design), stehen hier nie und bleiben unberührt.
CREATE TABLE IF NOT EXISTS jtl_abgleich_umfang (
    artikel_id INT UNSIGNED NOT NULL,
    lauf_id    INT UNSIGNED NOT NULL,
    PRIMARY KEY (artikel_id),
    CONSTRAINT fk_jau_artikel FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
