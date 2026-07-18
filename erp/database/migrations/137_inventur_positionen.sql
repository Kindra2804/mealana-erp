-- Migration 137: Inventur-Positionen (Zählliste) — Slice 2 des Inventur-Lauf-Kerns
--
-- Kein UNIQUE-Constraint auf (lauf, artikel, lager, lagerplatz, charge) — lagerplatz_id
-- und charge sind beide nullable, NULL != NULL in SQL-Unique-Indizes würde also
-- Duplikate durchlassen (gleiches Problem wie bei lagerbestand). Statt eines
-- fragilen Index prüft die Anwendungsschicht (InventurRepository::findPosition())
-- vor jedem Insert explizit per SELECT, ob die Position schon existiert.
--
-- lager_id ist Pflicht (auch wenn der Lauf-Scope kein Lager festlegt, z.B. bei
-- Kategorie/Artikel-Scope über mehrere Lager hinweg) — jede einzelne Zählposition
-- bezieht sich immer auf genau ein konkretes Lager.

CREATE TABLE inventur_positionen (
    id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    inventur_lauf_id INT UNSIGNED    NOT NULL,
    artikel_id       INT UNSIGNED    NOT NULL,
    lager_id         INT UNSIGNED    NOT NULL,
    lagerplatz_id    INT UNSIGNED    NULL,
    charge           VARCHAR(20)     NULL,
    soll_menge       DECIMAL(8,3)    NULL COMMENT 'Snapshot bei Aufnahme in die Zählliste, NULL wenn unbekannt (z.B. erster Lagerplatz-Zählgang)',
    ist_menge        DECIMAL(8,3)    NULL COMMENT 'NULL = noch nicht gezählt',
    status           ENUM('offen','gezaehlt') NOT NULL DEFAULT 'offen',
    notiz            VARCHAR(255)    NULL,
    gezaehlt_von     INT UNSIGNED    NULL,
    gezaehlt_am      TIMESTAMP       NULL,
    erstellt_am      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_invpos_lauf     FOREIGN KEY (inventur_lauf_id) REFERENCES inventur_laeufe (id) ON DELETE CASCADE,
    CONSTRAINT fk_invpos_artikel  FOREIGN KEY (artikel_id)       REFERENCES artikel (id)         ON UPDATE CASCADE,
    CONSTRAINT fk_invpos_lager    FOREIGN KEY (lager_id)         REFERENCES lager (id)           ON UPDATE CASCADE,
    CONSTRAINT fk_invpos_platz    FOREIGN KEY (lagerplatz_id)    REFERENCES lagerplaetze (id)    ON UPDATE CASCADE,
    CONSTRAINT fk_invpos_benutzer FOREIGN KEY (gezaehlt_von)     REFERENCES benutzer (id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
