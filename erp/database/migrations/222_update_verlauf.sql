-- 222: Update-Verlauf (··· → Updates). UpdateService legt die Tabelle beim ersten Update selbst an
-- (das Update AUF diese Version braucht sie schon vorher) — hier für Neuinstallationen und die Baseline.
CREATE TABLE IF NOT EXISTS update_verlauf (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    version_von      VARCHAR(20)  NOT NULL,
    version_nach     VARCHAR(20)  NOT NULL,
    status           ENUM('laeuft','erfolgreich','fehlgeschlagen','zurueckgespielt') NOT NULL,
    sicherung_ordner VARCHAR(100) NOT NULL COMMENT 'Ordner unter erp/storage/updates/ (Datenbank, alte Dateien, Paket)',
    meldung          TEXT         NULL,
    benutzer_id      INT UNSIGNED NULL,
    gestartet_am     DATETIME     NOT NULL,
    beendet_am       DATETIME     NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
