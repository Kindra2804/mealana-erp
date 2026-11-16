-- 254: Lang-Import -- Väter, die nicht importiert werden sollen (Babsi wählt im Probelauf ab, Jacky 2026-11-16).
-- Bleibt gespeichert, damit beim nächsten Lang-Import nicht wieder alles abgewählt werden muss.
-- quelle = 'lang', nummer = Lang-Vaternummer (z.B. "749"). Wiederholbar.

CREATE TABLE IF NOT EXISTS import_ausschluss (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    quelle      VARCHAR(30)  NOT NULL,
    nummer      VARCHAR(50)  NOT NULL,
    name        VARCHAR(255) NULL,
    benutzer_id INT UNSIGNED NULL,
    erstellt_am TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_import_ausschluss (quelle, nummer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
