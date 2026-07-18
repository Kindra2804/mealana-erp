-- Migration 133: Lieferanten-Guthaben-Konto (DROPS-Modell: Vorkasse, Teillieferung,
-- Restwert bleibt als Gutschrift beim Lieferanten stehen statt Rückzahlung/Nachlieferung)
--
-- Ersetzt bestellungen.gutschrift_betrag/_notiz (reine Freitext-Notiz auf EINER
-- Bestellung, kein Saldo über mehrere Bestellungen hinweg) durch ein echtes
-- Bewegungskonto pro Lieferant: betrag positiv = Gutschrift erhalten (bei
-- "Rest streichen" im Wareneingang), betrag negativ = bei einer späteren
-- Bestellung mit dem Guthaben verrechnet (siehe bestellung_zahlungen.art).
-- Saldo = SUM(betrag) pro Lieferant.

CREATE TABLE lieferanten_guthaben_bewegungen (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    lieferant_id  INT UNSIGNED    NOT NULL,
    bestellung_id INT UNSIGNED    NULL COMMENT 'Bestellung die die Bewegung ausgelöst hat',
    betrag        DECIMAL(10,2)   NOT NULL COMMENT 'positiv = Zugang, negativ = Verbrauch',
    typ           ENUM('gutschrift_erhalten','verrechnet') NOT NULL,
    datum         DATE            NOT NULL,
    notiz         VARCHAR(255)    NULL,
    erfasst_am    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    erfasst_von   INT UNSIGNED    NULL,
    CONSTRAINT fk_liefguth_lieferant  FOREIGN KEY (lieferant_id)  REFERENCES lieferanten(id)  ON UPDATE CASCADE,
    CONSTRAINT fk_liefguth_bestellung FOREIGN KEY (bestellung_id) REFERENCES bestellungen(id) ON DELETE SET NULL,
    CONSTRAINT fk_liefguth_benutzer   FOREIGN KEY (erfasst_von)   REFERENCES benutzer(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE bestellungen
    DROP COLUMN gutschrift_betrag,
    DROP COLUMN gutschrift_notiz;
