-- Migration 209: Partner-Umsätze im Buchhaltungs-Export (Partner-Abrechnung Schritt 5)
--
-- Klärung Steuerberatung 2026-10-26 (C10): Fremdumsätze sind KEINE Erlöse, Kommission ist
-- normaler Erlös. Der Export bucht daher je Bon-Position nach partner_modus:
--   fremdrechnung → Kassa/Bank an Verrechnungskonto Partner (Verbindlichkeit, ohne USt)
--   spende        → Kassa/Bank an Durchlaufende Spenden (Bar-Anteil in der Spendenlade
--                   wird nicht gebucht — eigene Aufzeichnung reicht, A6)
--   kommission    → Erlöskonto Kommissionsware (+ USt wie jeder Verkauf)
-- Auszahlungen an Partner: Verrechnungskonto/Spenden an Kassa bzw. Bank.
-- Kontonummern sind Vorschläge — mit Babsi bestätigen, änderbar unter Buchhaltung → Export.

INSERT INTO kontenplan (kontonummer, name, typ, aktiv)
SELECT '4080', 'Erlöse Kommissionsware', 'erloes', 1
WHERE NOT EXISTS (SELECT 1 FROM kontenplan WHERE kontonummer = '4080');

INSERT INTO kontenplan (kontonummer, name, typ, aktiv)
SELECT '3710', 'Verrechnung Partner Fremdumsätze', 'verbindlichkeit', 1
WHERE NOT EXISTS (SELECT 1 FROM kontenplan WHERE kontonummer = '3710');

INSERT INTO kontenplan (kontonummer, name, typ, aktiv)
SELECT '3720', 'Durchlaufende Spenden', 'verbindlichkeit', 1
WHERE NOT EXISTS (SELECT 1 FROM kontenplan WHERE kontonummer = '3720');

INSERT INTO system_einstellungen (schluessel, wert)
SELECT 'konto_partner_kommission', '4080'
WHERE NOT EXISTS (SELECT 1 FROM system_einstellungen WHERE schluessel = 'konto_partner_kommission');

INSERT INTO system_einstellungen (schluessel, wert)
SELECT 'konto_partner_fremd', '3710'
WHERE NOT EXISTS (SELECT 1 FROM system_einstellungen WHERE schluessel = 'konto_partner_fremd');

INSERT INTO system_einstellungen (schluessel, wert)
SELECT 'konto_partner_spende', '3720'
WHERE NOT EXISTS (SELECT 1 FROM system_einstellungen WHERE schluessel = 'konto_partner_spende');
