-- 260: Umbenennungen für Importe (Babsi/Jacky 2026-11-22)
--
-- Babsi hat beim Durchsehen des JTL-Bestands einzelne Artikelnummern korrigiert (z.B. "278480" -> "PL-278480",
-- "EL26-Apollo" -> "EL-26"). In JTL bleibt die alte Nummer stehen (JTL wird NICHT umbenannt), darum merkt sich
-- das ERP hier: Quelle + alte Nummer -> neue Nummer. Der JTL-Import und die Vorschau "JTL: neue Nummern" wenden
-- das an, bevor das neue Schema (Kürzel-Vater-Kind) gebildet wird.
-- Gegenstück zu import_ausschluss (Migr. 254): dort steht, was gar nicht übernommen wird.
-- nummer_alt = Nummer wie in der Quelle, ohne Auslauf-Markierung "#". Wiederholbar.

CREATE TABLE IF NOT EXISTS import_umbenennung (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    quelle      VARCHAR(30)  NOT NULL,
    nummer_alt  VARCHAR(50)  NOT NULL,
    nummer_neu  VARCHAR(50)  NOT NULL,
    name        VARCHAR(255) NULL,
    benutzer_id INT UNSIGNED NULL,
    erstellt_am TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_import_umbenennung (quelle, nummer_alt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
