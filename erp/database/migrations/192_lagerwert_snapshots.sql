-- Lagerwert-Schnappschüsse (Jacky 2026-10-02): festgehaltener Bestandswert zum Stichtag.
-- Anlässe: Monatsende (cron/lagerwert.php), Start und Abschluss jeder Inventur.
-- Bewertung je Artikel: letzter echter EK aus einem Wareneingang, sonst EK des
-- Standardlieferanten, sonst günstigster aktiver Lieferant, sonst EK des Vaters, sonst 0
-- (ek_quelle 'keiner' → Warnliste). Netto, wie Marge/Statistik.
-- Gezählt werden eigene Lager und Händler-Außenlager (bleibt bis zur Verkaufsmeldung unser
-- Eigentum), nie Partner-Lager und nie Partnerware.

CREATE TABLE IF NOT EXISTS lagerwert_snapshots (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    stichtag            DATETIME NOT NULL,
    anlass              ENUM('monatsende','inventur_start','inventur_abschluss') NOT NULL,
    inventur_lauf_id    INT NULL,
    wert_eigen          DECIMAL(14,2) NOT NULL DEFAULT 0,
    wert_haendler       DECIMAL(14,2) NOT NULL DEFAULT 0,
    menge_gesamt        DECIMAL(14,3) NOT NULL DEFAULT 0,
    artikel_anzahl      INT NOT NULL DEFAULT 0,
    artikel_ohne_ek     INT NOT NULL DEFAULT 0,
    benutzer_id         INT NULL,
    erstellt_am         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_stichtag (stichtag),
    KEY idx_lauf (inventur_lauf_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Summen je Lager (Lagername eingefroren, falls das Lager später umbenannt wird)
CREATE TABLE IF NOT EXISTS lagerwert_snapshot_lager (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_id         INT NOT NULL,
    lager_id            INT NOT NULL,
    lager_name          VARCHAR(150) NOT NULL,
    lager_beziehung     VARCHAR(30) NOT NULL,
    wert                DECIMAL(14,2) NOT NULL DEFAULT 0,
    menge               DECIMAL(14,3) NOT NULL DEFAULT 0,
    artikel_anzahl      INT NOT NULL DEFAULT 0,
    artikel_ohne_ek     INT NOT NULL DEFAULT 0,
    KEY idx_snapshot (snapshot_id),
    CONSTRAINT fk_lwsl_snapshot FOREIGN KEY (snapshot_id) REFERENCES lagerwert_snapshots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bewertete Bestandsliste je Artikel und Lager (Chargen zusammengefasst) — Grundlage
-- für den CSV-Export an den Steuerberater
CREATE TABLE IF NOT EXISTS lagerwert_snapshot_positionen (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_id         INT NOT NULL,
    artikel_id          INT NOT NULL,
    artikelnummer       VARCHAR(100) NOT NULL,
    artikel_name        VARCHAR(255) NOT NULL,
    lager_id            INT NOT NULL,
    menge               DECIMAL(14,3) NOT NULL,
    ek_netto            DECIMAL(10,4) NOT NULL DEFAULT 0,
    ek_quelle           ENUM('wareneingang','standardlieferant','lieferant','vater','keiner') NOT NULL,
    wert                DECIMAL(14,2) NOT NULL DEFAULT 0,
    KEY idx_snapshot (snapshot_id),
    CONSTRAINT fk_lwsp_snapshot FOREIGN KEY (snapshot_id) REFERENCES lagerwert_snapshots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
