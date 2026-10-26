-- Migration 208: Fachmiete als normale Rechnung (Partner-Abrechnung Schritt 4)
--
-- Klärung Babsi/Jacky 2026-10-26: Fachmiete ist eine ganz normale eigene Rechnung an den
-- Partner (20 %, Mieterlöse), NIE mit Verkäufen verrechnet. Monatlich im Voraus, erstellt
-- 2 Tage vor Monatsende für den Folgemonat. Mietbeginn immer Monatserster, Kündigung immer
-- zum Monatsletzten → keine Anteile.
-- Umsetzung: je Partner und Monat ein Auftrag (Kunde = partner.kunde_id) mit einer Zeile je
-- Fach (Artikel FACHMIETE) + Vollrechnung — Mahnwesen/Zahlung buchen/Kasse-Zahlbeleg
-- funktionieren damit wie bei jeder Rechnung.

-- Erlöskonto + Artikelgruppe (Kontonummer in Buchhaltung → Artikelgruppen änderbar)
INSERT INTO kontenplan (kontonummer, name, typ, aktiv)
SELECT '4800', 'Mieterlöse Fachmiete', 'erloes', 1
WHERE NOT EXISTS (SELECT 1 FROM kontenplan WHERE kontonummer = '4800');

INSERT INTO artikel_gruppen (name, konto_nr, aktiv, an_kasse_waehlbar, sortierung)
SELECT 'Fachmiete', '4800', 1, 0, 97
WHERE NOT EXISTS (SELECT 1 FROM artikel_gruppen WHERE name = 'Fachmiete');

-- Leistungsartikel ohne Lagerführung, keinem Shop zugewiesen (nie im Onlineshop)
INSERT INTO artikel (artikelnummer, name, artikeltyp_id, artikel_gruppe_id, steuerklasse_id, einheit_id,
                     keine_lagerbestandsfuehrung, aktiv)
SELECT 'FACHMIETE', 'Fachmiete',
       (SELECT id FROM artikel_typen WHERE code = 'STANDARD' LIMIT 1),
       (SELECT id FROM artikel_gruppen WHERE name = 'Fachmiete' LIMIT 1),
       (SELECT id FROM steuerklassen WHERE satz = 20 ORDER BY id LIMIT 1),
       (SELECT id FROM einheiten WHERE name LIKE 'St%ck' ORDER BY id LIMIT 1),
       1, 1
WHERE NOT EXISTS (SELECT 1 FROM artikel WHERE artikelnummer = 'FACHMIETE');

-- miet_rechnungen = Nachweis "Vertrag X für Monat Y verrechnet" (verhindert Doppelrechnungen).
-- Mehrere Fächer eines Partners stehen auf EINER Rechnung → rechnungs_nr nicht mehr eindeutig.
ALTER TABLE miet_rechnungen
    DROP INDEX uk_mietrg_nr,
    ADD COLUMN vertrag_id  INT UNSIGNED NULL AFTER id,
    ADD COLUMN auftrag_id  INT UNSIGNED NULL AFTER partner_id,
    ADD COLUMN rechnung_id INT UNSIGNED NULL AFTER auftrag_id,
    ADD UNIQUE KEY uk_mietrg_vertrag_periode (vertrag_id, periode),
    ADD INDEX idx_mietrg_auftrag (auftrag_id);

-- Vorlauf: so viele Tage vor Monatsende wird der Folgemonat verrechnet
INSERT INTO system_einstellungen (schluessel, wert)
SELECT 'fachmiete_vorlauf_tage', '2'
WHERE NOT EXISTS (SELECT 1 FROM system_einstellungen WHERE schluessel = 'fachmiete_vorlauf_tage');
