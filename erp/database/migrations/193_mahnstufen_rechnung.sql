-- Mahnstufen für Rechnungskunden (Jacky 2026-10-02): Zahlungserinnerung → 1. Mahnung →
-- 2. (letzte) Mahnung, danach "manuell klären". Fristen ab Fälligkeit der Rechnung:
-- Erinnerung nach 7 Tagen (automatisch), 1. und 2. Mahnung je 14 Tage nach der Vorstufe
-- als VORSCHLAG — erst die Freigabe (Verkauf → Mahnwesen) verschickt PDF + Mail.
-- Nur Mahngebühr, keine Verzugszinsen. Vorkasse bleibt wie bisher (Erinnerung 14 Tage,
-- Auto-Storno 30 Tage nach Bestellung).

ALTER TABLE mahnungen
    MODIFY typ ENUM('erinnerung','stornierung','hinweis','mahnung1','mahnung2') NOT NULL,
    -- Vorschläge haben noch kein Versanddatum
    MODIFY gesendet_am DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN status ENUM('vorgeschlagen','versendet','verworfen') NOT NULL DEFAULT 'versendet' AFTER typ,
    ADD COLUMN vorgeschlagen_am DATETIME NULL AFTER status,
    ADD COLUMN offen_betrag DECIMAL(10,2) NULL COMMENT 'offener Rechnungsbetrag beim Versand' AFTER mail_an,
    ADD COLUMN gebuehr DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER offen_betrag,
    ADD COLUMN gebuehr_erlassen_am DATETIME NULL AFTER gebuehr,
    ADD COLUMN neue_frist DATE NULL AFTER gebuehr_erlassen_am,
    ADD COLUMN dateiname VARCHAR(255) NULL AFTER neue_frist,
    ADD COLUMN bearbeitet_von INT NULL COMMENT 'wer freigegeben/verworfen/erlassen hat' AFTER erstellt_von,
    ADD KEY idx_status (status);

-- Fälligkeit wurde bisher nie gespeichert (Rechnung druckte pauschal +14 Tage) — Altbestand
-- mit genau diesem Wert nachziehen, damit das Mahnwesen eine Basis hat
UPDATE rechnungen SET faellig_am = DATE_ADD(DATE(erstellt_am), INTERVAL 14 DAY) WHERE faellig_am IS NULL;

-- Einstellungen (Einstellungen → Mahnwesen)
INSERT INTO system_einstellungen (schluessel, wert)
SELECT t.k, t.v FROM (
    SELECT 'mahnung_erinnerung_tage' k, '7' v
    UNION ALL SELECT 'mahnung_stufe1_tage', '14'
    UNION ALL SELECT 'mahnung_stufe2_tage', '14'
    UNION ALL SELECT 'mahnung_gebuehr_stufe1', '5.00'
    UNION ALL SELECT 'mahnung_gebuehr_stufe2', '10.00'
) t
WHERE NOT EXISTS (SELECT 1 FROM system_einstellungen s WHERE s.schluessel = t.k);

-- Erlöskonto für Mahngebühren (nicht umsatzsteuerbar — Schadenersatz). Kontonummer bitte mit
-- dem Steuerberater abstimmen; zugeordnet wie Versandkosten über eine eigene Artikelgruppe.
INSERT INTO kontenplan (kontonummer, name, typ, aktiv)
SELECT '4890', 'Mahngebühren', 'erloes', 1
WHERE NOT EXISTS (SELECT 1 FROM kontenplan WHERE kontonummer = '4890');

INSERT INTO artikel_gruppen (name, konto_nr, aktiv, sortierung)
SELECT 'Mahngebühren', '4890', 1, 98
WHERE NOT EXISTS (SELECT 1 FROM artikel_gruppen WHERE name = 'Mahngebühren');
