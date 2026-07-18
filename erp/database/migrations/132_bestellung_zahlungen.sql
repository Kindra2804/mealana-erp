-- Migration 132: Teilzahlungen für Lieferantenrechnungen (Einkauf)
-- Ersetzt den einfachen rechnung_bezahlt_am-Flag (Migration 131) — der reichte nicht,
-- sobald ein Teil einer Rechnung aus Lieferanten-Guthaben statt neuer Überweisung
-- beglichen wird (DROPS-Modell, siehe lieferanten_guthaben_bewegungen / Migration 133).
-- Spiegelt exakt auftrag_zahlungen (Migration 076), nur mit zusätzlicher "art"-Spalte.

CREATE TABLE bestellung_zahlungen (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    bestellung_id INT UNSIGNED    NOT NULL,
    betrag        DECIMAL(10,2)   NOT NULL,
    art           ENUM('ueberweisung','guthaben_verrechnung') NOT NULL DEFAULT 'ueberweisung',
    buchungsdatum DATE            NOT NULL,
    notiz         VARCHAR(255)    NULL,
    erfasst_am    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    erfasst_von   INT UNSIGNED    NULL,
    CONSTRAINT fk_bezahl_bestellung FOREIGN KEY (bestellung_id) REFERENCES bestellungen(id) ON DELETE CASCADE,
    CONSTRAINT fk_bezahl_benutzer   FOREIGN KEY (erfasst_von)   REFERENCES benutzer(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE bestellungen DROP COLUMN rechnung_bezahlt_am;
