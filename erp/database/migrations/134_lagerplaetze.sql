-- Migration 134: Lagerplätze (Regal/Fach-Struktur unterhalb eines Lagers)
-- Voraussetzung für das Inventur-Modul (siehe Memory project_inventur_konzept):
-- mehrere gleichzeitige Zähler brauchen eine Orts-Aufteilung, sonst nicht steuerbar
-- wer welchen Bereich schon gezählt hat.
--
-- Bewusst NUR die Stammdaten-Tabelle in diesem Schritt — die Verknüpfung mit
-- lagerbestand (lagerplatz_id) kommt erst mit dem Inventur-Lauf-Kern, weil sie
-- die bestehende UNIQUE(artikel_id, lager_id, charge)-Regel berührt, die
-- Kasse/Wareneingang/Umlagerung aktuell produktiv nutzen — das gehört zusammen
-- mit den dortigen Anpassungen in einen eigenen, gezielt getesteten Schritt.

CREATE TABLE lagerplaetze (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    lager_id    INT UNSIGNED    NOT NULL,
    bezeichnung VARCHAR(50)     NOT NULL,
    aktiv       TINYINT(1)      NOT NULL DEFAULT 1,
    erstellt_am TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_lagerplatz_lager FOREIGN KEY (lager_id) REFERENCES lager (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
