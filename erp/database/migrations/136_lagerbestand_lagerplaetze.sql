-- Migration 136: Lagerbestand-Verteilung auf Lagerplätze (additiv, siehe project_inventur_konzept)
-- Bewusst eine EIGENE Tabelle statt lagerbestand.lagerplatz_id — dadurch bleiben
-- Kasse/Wareneingang/Umlagerung komplett unberührt. Summe über alle Lagerplätze
-- einer lagerbestand-Zeile ergibt (soll) den lagerbestand.bestand-Wert; wird
-- von der Inventur gepflegt, nicht von den bestehenden Buchungspfaden.

CREATE TABLE lagerbestand_lagerplaetze (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    lagerbestand_id INT UNSIGNED    NOT NULL,
    lagerplatz_id   INT UNSIGNED    NOT NULL,
    menge           DECIMAL(8,3)    NOT NULL DEFAULT 0,
    geaendert_am    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_lbl_bestand_platz (lagerbestand_id, lagerplatz_id),
    CONSTRAINT fk_lbl_bestand FOREIGN KEY (lagerbestand_id) REFERENCES lagerbestand (id)  ON DELETE CASCADE,
    CONSTRAINT fk_lbl_platz   FOREIGN KEY (lagerplatz_id)   REFERENCES lagerplaetze (id)  ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
