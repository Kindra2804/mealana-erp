-- Migration 124: PDF-Dokumente für Lieferantenbestellungen (analog auftrag_dokumente)
-- Eigene Tabelle statt Wiederverwendung von auftrag_dokumente, weil sonst die
-- gemeinsame ID zwischen auftraege und bestellungen im Storage-Pfad kollidieren könnte.

CREATE TABLE bestellung_dokumente (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bestellung_id  INT UNSIGNED  NOT NULL,
    typ            ENUM('bestellung') NOT NULL DEFAULT 'bestellung',
    dateiname      VARCHAR(255)  NOT NULL,
    mail_gesendet_am TIMESTAMP   NULL,
    erstellt_am    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von   INT UNSIGNED  NOT NULL,

    CONSTRAINT fk_bdok_bestellung FOREIGN KEY (bestellung_id) REFERENCES bestellungen (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bdok_benutzer   FOREIGN KEY (erstellt_von)   REFERENCES benutzer (id)     ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
