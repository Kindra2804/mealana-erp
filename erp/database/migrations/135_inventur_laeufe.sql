-- Migration 135: Inventur-Läufe (Kopftabelle) — Slice 1 des Inventur-Lauf-Kerns
-- Siehe Memory project_inventur_konzept: EIN Scope-flexibler Mechanismus statt
-- getrennter Module für große Inventur / rollierende Inventur / Einzelartikel-Nachzählung.
--
-- scope_tabelle/scope_id: polymorphe Referenz (wie aktivitaeten.referenz_tabelle/
-- referenz_id) statt FK, weil der Scope wahlweise auf lager, lagerplaetze,
-- kategorien, artikel oder mietfaecher zeigt.
-- scope_bezeichnung: Snapshot des Namens zum Startzeitpunkt (wie kunden_snapshot),
-- damit die Lauf-Historie auch nach Umbenennung/Löschung des Scope-Ziels lesbar bleibt.
-- vorgaenger_lauf_id: verweist auf einen pausierten/abgebrochenen Lauf, wenn dieser
-- Lauf als "Fortsetzung — nur fehlende Teile" davon gestartet wurde.

CREATE TABLE inventur_laeufe (
    id                 INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    scope_tabelle      VARCHAR(20)     NOT NULL COMMENT 'lager | lagerplaetze | kategorien | artikel | mietfaecher',
    scope_id           INT UNSIGNED    NOT NULL,
    scope_bezeichnung  VARCHAR(150)    NOT NULL,
    blind_modus        TINYINT(1)      NOT NULL DEFAULT 1,
    status             ENUM('laufend','pausiert','abgeschlossen','abgebrochen') NOT NULL DEFAULT 'laufend',
    vorgaenger_lauf_id INT UNSIGNED    NULL,
    notiz              TEXT            NULL,
    benutzer_id        INT UNSIGNED    NOT NULL,
    gestartet_am       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    beendet_am         TIMESTAMP       NULL,

    CONSTRAINT fk_invlauf_benutzer   FOREIGN KEY (benutzer_id)        REFERENCES benutzer (id)        ON UPDATE CASCADE,
    CONSTRAINT fk_invlauf_vorgaenger FOREIGN KEY (vorgaenger_lauf_id) REFERENCES inventur_laeufe (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
