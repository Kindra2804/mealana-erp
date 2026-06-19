-- MeaLana ERP – Testdaten löschen
-- Führt TRUNCATE auf alle Artikel/Kunden/Aktions-Daten durch.
-- BEHALTEN: kategorien, steuerklassen, artikel_typen, einheiten, kundengruppen,
--            lager, hersteller, merkmal_gruppen, merkmale, merkmal_werte,
--            merkmal_artikeltypen, versandklassen, zahlungsbedingungen,
--            benutzer, rollen, berechtigungen, rollen_berechtigungen, benutzer_rollen,
--            system_einstellungen
--
-- Ausführen: In phpMyAdmin → SQL-Reiter einfügen und ausführen
-- Oder: mysql -u root mealana_erp < truncate_testdaten.sql

SET FOREIGN_KEY_CHECKS = 0;

-- Aktionen
DELETE FROM aktionen_artikel_preise;
DELETE FROM aktionen_kategorien;
DELETE FROM aktionen;
DELETE FROM preis_aktionen_positionen;

-- Kunden
DELETE FROM kunden_dsgvo_consent;
DELETE FROM kunden_ansprechpartner;
DELETE FROM kunden_adressen;
DELETE FROM kunden_shops;
DELETE FROM kunden_merge_queue;
DELETE FROM kunden;

-- Varianten / Achsen
DELETE FROM varianten_kombination_werte;
DELETE FROM artikel_achsen;
DELETE FROM varianten_achse_werte;
DELETE FROM varianten_achsen;

-- Artikel-Relationen
DELETE FROM artikel_bilder_shops;
DELETE FROM artikel_bilder;
DELETE FROM artikel_staffelpreise;
DELETE FROM artikel_preise;
DELETE FROM artikel_merkmale;
DELETE FROM artikel_lieferanten;
DELETE FROM artikel_kategorien;
DELETE FROM artikel_codes;
DELETE FROM artikel_externe_referenzen;

-- Lager
DELETE FROM reservierungen;
DELETE FROM lager_bewegungen;
DELETE FROM lagerbestand;

-- Lieferanten
DELETE FROM lieferanten_vertreter;
DELETE FROM lieferanten;

-- Artikel (Kinder zuerst wegen selbstreferentieller FK vaterartikel_id → artikel.id)
DELETE FROM artikel WHERE vaterartikel_id IS NOT NULL;
DELETE FROM artikel;

-- Sessions + Aktivitäten-Log
DELETE FROM sessions;
DELETE FROM aktivitaeten;

-- Benutzer-Einstellungen (Spalten-Picker etc.)
DELETE FROM benutzer_einstellungen;

SET FOREIGN_KEY_CHECKS = 1;

-- Was übrig bleibt:
-- ✅ kategorien          – Kategoriebaum vollständig erhalten
-- ✅ steuerklassen       – 20% / 10%
-- ✅ artikel_typen       – GARN / NADEL / etc.
-- ✅ einheiten           – Stk, g, m, ...
-- ✅ kundengruppen       – Endkunde, Händler, ...
-- ✅ lager               – Ladengeschäft, Messe, ...
-- ✅ hersteller          – Drops, Lang Yarns, ...
-- ✅ merkmal_gruppen     – Fasergehalt, Maschenprobe, ...
-- ✅ merkmale            – Einzelne Merkmale
-- ✅ merkmal_werte       – Merkmal-Werte
-- ✅ merkmal_artikeltypen
-- ✅ versandklassen
-- ✅ zahlungsbedingungen
-- ✅ benutzer            – Jacky, Barbara, System
-- ✅ rollen / berechtigungen / rollen_berechtigungen / benutzer_rollen
-- ✅ system_einstellungen – Kleinunternehmer-Modus etc.
