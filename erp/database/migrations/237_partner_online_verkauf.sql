-- 237: Partner verkaufen online im eigenen Namen (Jacky 2026-11-13, Indra-Design = EU von Babsi ab 15.11.)
-- Bisher war Partnerware nur Kassenverkauf. Ein Partner mit online_verkauf = 1 darf Artikel im Shop haben;
-- Shop-Bestellungen seiner Ware werden Partner-Aufträge (auftraege.partner_id) mit Belegen in SEINEM Namen
-- (Briefkopf/Fußzeile aus den Partnerdaten, eigener Nummernkreis R-<Kürzel>-JJJJ-nnnnn, KU-Text).
-- Kein Erlös der KG: Zahlungen gehen über das Partner-Konto (3710). Versandkosten verrechnet die KG
-- dem Partner monatlich (eigene Rechnung, wie die Fachmiete).
-- Wiederholbar (IF NOT EXISTS).

ALTER TABLE partner
    ADD COLUMN IF NOT EXISTS online_verkauf TINYINT(1)   NOT NULL DEFAULT 0 AFTER typ,
    ADD COLUMN IF NOT EXISTS nr_kuerzel     VARCHAR(10)  NULL DEFAULT NULL AFTER online_verkauf,  -- Nummernkreis, 5-10 Zeichen A-Z/0-9, gesperrt ab 1. Beleg
    ADD COLUMN IF NOT EXISTS bic            VARCHAR(11)  NULL DEFAULT NULL AFTER iban,
    ADD COLUMN IF NOT EXISTS bank_name      VARCHAR(100) NULL DEFAULT NULL AFTER bic,
    ADD COLUMN IF NOT EXISTS website        VARCHAR(200) NULL DEFAULT NULL AFTER telefon,
    ADD COLUMN IF NOT EXISTS steuernummer   VARCHAR(30)  NULL DEFAULT NULL AFTER uid_nummer,
    ADD UNIQUE KEY IF NOT EXISTS uq_partner_nr_kuerzel (nr_kuerzel);

-- Partner-Auftrag: alle Positionen gehören diesem Partner (Belege in seinem Namen)
ALTER TABLE auftraege
    ADD COLUMN IF NOT EXISTS partner_id INT UNSIGNED NULL DEFAULT NULL AFTER shop_id,
    ADD KEY IF NOT EXISTS idx_auftraege_partner (partner_id);
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'auftraege' AND CONSTRAINT_NAME = 'fk_auftraege_partner');
SET @sql := IF(@fk = 0, 'ALTER TABLE auftraege ADD CONSTRAINT fk_auftraege_partner FOREIGN KEY (partner_id) REFERENCES partner (id)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Längere Kürzel (bis 10) in den Belegnummern: R-INDRA-2026-00001, GS-XXXXXXXXXX-2026-00001
ALTER TABLE dokument_nummern MODIFY kuerzel VARCHAR(10) NOT NULL DEFAULT '';
ALTER TABLE rechnungen       MODIFY rechnung_nr   VARCHAR(30) NOT NULL;
ALTER TABLE gutschriften     MODIFY gutschrift_nr VARCHAR(30) NOT NULL;
