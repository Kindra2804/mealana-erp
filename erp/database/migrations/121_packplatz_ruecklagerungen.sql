-- Warteschlange für physische Rücklagerung nach Kassen-Retouren: die Kasse bucht bei
-- block='retour'-Positionen NUR den finanziellen Ausgleich (kein_lagerabzug=true), damit
-- Ware, die tatsächlich am Tresen zurückkommt, nicht unkontrolliert ohne Bestandsbuchung
-- ins Regal wandert. Eine Zeile pro zurückgenommener Position — sowohl für Auftrag-
-- gebundene Retouren (versendet/teilgeliefert/abgeschlossen, siehe bon_speichern.php)
-- als auch für die Freitext-Retour ohne Auftragsbezug (auftrag_id dann NULL).
CREATE TABLE packplatz_ruecklagerungen (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kassen_bon_id      INT UNSIGNED NOT NULL,
    bon_nr             VARCHAR(30) NOT NULL,
    auftrag_id         INT UNSIGNED DEFAULT NULL,
    auftrag_nr         VARCHAR(20) DEFAULT NULL,
    artikel_id         INT UNSIGNED NOT NULL,
    bezeichnung        VARCHAR(255) NOT NULL,
    menge              INT UNSIGNED NOT NULL,
    charge             VARCHAR(50) DEFAULT NULL,
    kasse_id           INT UNSIGNED NOT NULL,
    status             ENUM('offen','erledigt') NOT NULL DEFAULT 'offen',
    erledigt_am        DATETIME DEFAULT NULL,
    erledigt_von       INT UNSIGNED DEFAULT NULL,
    erledigt_lager_id  INT UNSIGNED DEFAULT NULL,
    erledigt_zustand   ENUM('neu','gebraucht','beschaedigt','defekt') DEFAULT NULL,
    erstellt_am        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pr_status (status),
    KEY idx_pr_bon (kassen_bon_id),
    CONSTRAINT fk_pr_bon FOREIGN KEY (kassen_bon_id) REFERENCES kassen_bons(id),
    CONSTRAINT fk_pr_auftrag FOREIGN KEY (auftrag_id) REFERENCES auftraege(id),
    CONSTRAINT fk_pr_artikel FOREIGN KEY (artikel_id) REFERENCES artikel(id),
    CONSTRAINT fk_pr_kasse FOREIGN KEY (kasse_id) REFERENCES kassen(id),
    CONSTRAINT fk_pr_erledigt_von FOREIGN KEY (erledigt_von) REFERENCES benutzer(id),
    CONSTRAINT fk_pr_erledigt_lager FOREIGN KEY (erledigt_lager_id) REFERENCES lager(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
