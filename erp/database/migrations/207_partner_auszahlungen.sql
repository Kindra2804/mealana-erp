-- Migration 207: Partner-Konto — Auszahlungen an Partner (Partner-Abrechnung Schritt 3)
--
-- Fremdrechnung + Spende: das Geld gehört dem Partner, wir verwahren es nur
-- (Klärung Steuerberatung 2026-10-26). Saldo = Verkäufe (Bon-Positionen) − Auszahlungen.
-- Jede Auszahlung bekommt eine Zahlungsaviso-Nummer (ZA-…).
--   bar_kasse    → Barausgabe aus einer Kassenlade (Kassenbuch-Entnahme)
--   spendenlade  → Übergabe des Spendenlade-Inhalts (nicht im Kassenstand, kein Kassenbuch)
--   ueberweisung → nur vermerkt, die Überweisung selbst läuft über die Bank
-- Kommission hat KEINE Auszahlung hier: der Partner stellt Rechnung, die wie ein Einkauf
-- bezahlt wird.

ALTER TABLE dokument_nummern
    MODIFY typ ENUM('rechnung','gutschrift','lieferschein','mietrechnung','abrechnung','auftrag','pickliste',
                    'partner_uebernahme','partner_rueckgabe','haendler_lieferung','haendler_ruecknahme','haendler_schwund',
                    'partner_auszahlung') NOT NULL;
INSERT INTO dokument_nummern (typ, praefix, jahr, letzt_nr)
SELECT 'partner_auszahlung', 'ZA', YEAR(CURDATE()), 0
WHERE NOT EXISTS (SELECT 1 FROM dokument_nummern WHERE typ = 'partner_auszahlung');

CREATE TABLE IF NOT EXISTS partner_auszahlungen (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    partner_id     INT UNSIGNED NOT NULL,
    nummer         VARCHAR(30)  NOT NULL,                    -- Zahlungsaviso ZA-…
    datum          DATE         NOT NULL,
    betrag         DECIMAL(10,2) NOT NULL,
    art            ENUM('bar_kasse','spendenlade','ueberweisung') NOT NULL,
    zeitraum_von   DATE         NULL,                        -- für welche Verkäufe (Info am Aviso)
    zeitraum_bis   DATE         NULL,
    kasse_id       INT UNSIGNED NULL,                        -- nur bar_kasse
    kassenbuch_id  INT UNSIGNED NULL,                        -- Entnahme-Buchung (bar_kasse)
    notiz          VARCHAR(500) NULL,
    storniert      TINYINT(1)   NOT NULL DEFAULT 0,
    storno_kassenbuch_id INT UNSIGNED NULL,                  -- Gegenbuchung (Einlage) beim Storno
    benutzer_id    INT UNSIGNED NULL,
    erstellt_am    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pa_nummer (nummer),
    INDEX idx_pa_partner (partner_id, storniert),
    CONSTRAINT fk_pa_partner FOREIGN KEY (partner_id) REFERENCES partner(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
