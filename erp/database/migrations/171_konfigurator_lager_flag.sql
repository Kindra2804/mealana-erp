-- Konfigurator Phase 1: generisches "kein eigener Lagerbestand"-Flag am Artikel
-- (auf Bestellung gefertigte Artikel wie Schilder-Layouts, aber auch für andere
-- Artikeltypen ohne Lagerführung wiederverwendbar) + Aufräumarbeiten an
-- position_konfiguration vor der ersten echten Nutzung.

ALTER TABLE artikel
    ADD COLUMN keine_lagerbestandsfuehrung TINYINT(1) NOT NULL DEFAULT 0 AFTER ist_konfigurierbar;

ALTER TABLE position_konfiguration
    ADD INDEX idx_poskonfig_referenz (referenz_tabelle, referenz_id),
    ADD COLUMN wert_text VARCHAR(100) NULL AFTER wert_id;
