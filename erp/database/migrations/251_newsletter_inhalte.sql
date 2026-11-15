-- 251: Newsletter Schritt 3 — Inhalte aus Bausteinen (Jacky 2026-11-15)
-- bausteine = JSON-Liste [{typ: kopfbild|text|bild|knopf|artikel|hinweis|trenner, …}], gerendert im Design
-- des Shops (Logo, Hausfarbe, Website aus dem Kanal). Versand kommt mit Schritt 4.
-- Wiederholbar.

CREATE TABLE IF NOT EXISTS newsletter (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shop_id       INT UNSIGNED NOT NULL,
    titel         VARCHAR(200) NOT NULL,                 -- interner Name („Advent 2026“)
    betreff       VARCHAR(200) NULL,
    vorschautext  VARCHAR(200) NULL,                     -- Text, den Mailprogramme neben dem Betreff zeigen
    bausteine     LONGTEXT     NULL,
    status        ENUM('entwurf','versand_laeuft','versendet') NOT NULL DEFAULT 'entwurf',
    testmail_am   DATETIME     NULL,
    erstellt_von  INT UNSIGNED NULL,
    erstellt_am   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    geaendert_am  TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_nl_shop (shop_id, status),
    CONSTRAINT fk_nl_shop FOREIGN KEY (shop_id) REFERENCES shops (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
