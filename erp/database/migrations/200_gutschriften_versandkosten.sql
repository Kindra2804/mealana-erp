-- Rechnungskorrektur/Stornorechnung kann auch die Versandkosten erstatten (Klicktest
-- 2026-10-07, A-2026-00067: Komplettstorno ohne Versand). Brutto wie rechnungen.versandkosten_brutto;
-- Steueraufteilung wie auf der Rechnung (Versandsteuer, Satz der überwiegenden Leistung).
ALTER TABLE gutschriften
    ADD COLUMN versandkosten_brutto DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER bruttobetrag;
