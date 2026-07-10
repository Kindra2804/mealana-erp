-- Migration 125: Live-Status pro Kasse für die Kundenanzeige (Zweitbildschirm-Tablet).
-- Der Warenkorb existierte bisher nur im Browser-JS der Kasse (bon.php), nirgends
-- serverseitig — die Kasse schreibt bei jeder Warenkorb-Änderung hierher, das
-- Kundenanzeige-Tablet pollt diese eine Zeile alle ~1s. Eine Zeile pro Kasse reicht
-- (kein Verlauf nötig, immer nur der aktuelle Stand).
CREATE TABLE kassen_live_state (
    kasse_id        INT UNSIGNED NOT NULL PRIMARY KEY,
    zustand         ENUM('idle','warenkorb','abrechnen') NOT NULL DEFAULT 'idle',
    payload         JSON NULL,
    aktualisiert_am TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_kls_kasse FOREIGN KEY (kasse_id) REFERENCES kassen (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
