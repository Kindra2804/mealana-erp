-- 257: JTL-Kunden- und Archiv-Import im Browser (Kunden → ⬇ JTL-Import, Jacky 2026-11-19)
--
-- Solange MeaLana-Shop und Laden auf JTL laufen, wird der Import wiederholt. Statt der zwei
-- Kommandozeilen-Skripte gibt es eine Seite: Datei stückweise hochladen, Probelauf, Import in
-- Abschnitten (die Datei wird ab der gemerkten Stelle weitergelesen -> auch 90 MB ohne
-- Speicher-/Zeitlimit, nach Abbruch fortsetzbar).
--
-- jtl_import_laeufe: ein Upload + seine Durchgänge. art = kunden | auftraege,
--   position = Byte-Stelle in der Datei, bis zu der verarbeitet ist; zahlen = Summen als JSON.
-- jtl_import_offen: Archiv-Aufträge dieses Laufs, die JÜNGER als 365 Tage und laut JTL unbezahlt sind.
--   Ältere unbezahlte gelten beim Import fix als bezahlt (LS-POS/JTL-Lücke, Jacky 2026-11-19).
--   Die Liste zeigt alle vorab als „erledigt“ angehakt; nur die wirklich offenen werden abgehakt.
--   entscheidung NULL = noch nicht durchgesehen, 'bezahlt' = auf bezahlt gesetzt, 'offen' = bleibt offen.

CREATE TABLE IF NOT EXISTS jtl_import_laeufe (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    art           ENUM('kunden','auftraege') NOT NULL,
    datei         VARCHAR(255) NOT NULL,
    dateiname     VARCHAR(255) NOT NULL,
    groesse       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM('hochgeladen','probelauf','fertig_probelauf','import','fertig') NOT NULL DEFAULT 'hochgeladen',
    position      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    zahlen        JSON NULL,
    fehler        JSON NULL,
    benutzer_id   INT UNSIGNED NULL,
    erstellt_am   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    geaendert_am  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS jtl_import_offen (
    lauf_id       INT UNSIGNED NOT NULL,
    auftrag_id    INT UNSIGNED NOT NULL,
    entscheidung  ENUM('bezahlt','offen') NULL,
    entschieden_am DATETIME NULL,
    PRIMARY KEY (lauf_id, auftrag_id),
    KEY idx_jio_auftrag (auftrag_id),
    CONSTRAINT fk_jio_lauf    FOREIGN KEY (lauf_id)    REFERENCES jtl_import_laeufe (id) ON DELETE CASCADE,
    CONSTRAINT fk_jio_auftrag FOREIGN KEY (auftrag_id) REFERENCES auftraege (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
