-- 242: Mengen-Aktionen – dritte Art "Paketpreise" mit mehreren Paketgrößen (Jacky 2026-11-14)
-- Fall Raunzmarker (12 Varianten gemischt): 1 Stk 2,50 · 2 für 4,50 · 4 für 8 · 8 für 15 · 12 für 20 €.
-- Die Menge wird so aus Paketen zusammengesetzt, dass der Preis am günstigsten ist, der Rest einzeln
-- („schöne“ Preise: 5 Stk = 8,00 + 2,50 = 10,50, nicht 5 × 2,00). Rechnung: MengenAktionRechner::paketPlan().
-- mengen_aktionen.menge = kleinste Paketgröße. Wiederholbar.

ALTER TABLE mengen_aktionen
    MODIFY art ENUM('x_fuer_y', 'kauf_x_zahl_y', 'paketpreise') NOT NULL;

CREATE TABLE IF NOT EXISTS mengen_aktion_pakete (
    aktion_id  INT UNSIGNED NOT NULL,
    menge      SMALLINT UNSIGNED NOT NULL,   -- Stück im Paket
    preis      DECIMAL(8,2) NOT NULL,        -- Paketpreis brutto
    PRIMARY KEY (aktion_id, menge),
    FOREIGN KEY (aktion_id) REFERENCES mengen_aktionen(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
