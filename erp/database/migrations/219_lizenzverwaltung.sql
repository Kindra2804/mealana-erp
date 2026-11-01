-- Migration 219: Lizenzverwaltung / Generator (Lizenz-Bau Schritt 4)
--
-- Jacky 2026-11-01: Lizenzen verkauft er aus seinem eigenen ERP: Kunde = normaler ERP-Kunde,
-- Module = Artikel (Typ "Lizenz", eigene Kategorie, ohne Lager, nie im Shop, später wie andere
-- Artikel änderbar: Preise, Sonderpreise, Händler), Verkauf = normaler Auftrag + Rechnung,
-- daraus "Lizenzdatei erstellen". Der Generator ist nur sichtbar, wenn der Pfad zum privaten
-- Schlüssel in den Einstellungen steht und die Datei existiert — in Kunden-Installationen nie.
--
-- Lizenznummer L-PPNNNN-CC (Jacky 2026-11-01): PP = Produkt (10 = NAHTLOS ERP), NNNN =
-- fortlaufend über alle Lizenzen, CC = Prüfziffer mod 97 (wie IBAN). Bleibt je Kunden-
-- installation für immer gleich; jede neu ausgestellte Datei ist eine "Ausgabe" (1, 2, 3 …).
-- Preise netto lt. Jacky 2026-11-01, BFR = Fremdartikel (nur weiterverrechnet, keine Lizenz).

-- Artikeltyp, Kategorie, Erlöskonto/Artikelgruppe (Konto in Buchhaltung → Artikelgruppen änderbar)
-- Kategorie, Konto, Artikelgruppe und die Lizenz-Artikel nur in der NAHTLOS/MeaLana-Installation (verkauft die
-- Lizenzen) — Kunden-Installationen bekommen nur den Artikeltyp und die (leeren) Lizenz-Tabellen.
INSERT INTO artikel_typen (code, name, teilbar, hat_varianten, hat_lagerstand, ist_download, ist_set, ist_gutschein, sortierung, aktiv)
SELECT 'LIZENZ', 'Lizenz', 0, 0, 0, 0, 0, 0, 8, 1
WHERE NOT EXISTS (SELECT 1 FROM artikel_typen WHERE code = 'LIZENZ');

INSERT INTO kategorien (parent_id, name, sortierung, aktiv)
SELECT NULL, 'Lizenzen', 990, 1
WHERE NOT EXISTS (SELECT 1 FROM kategorien WHERE name = 'Lizenzen' AND parent_id IS NULL)
  AND EXISTS (SELECT 1 FROM system_einstellungen WHERE schluessel = 'firmenname' AND wert LIKE 'MEALANA%');

INSERT INTO kontenplan (kontonummer, name, typ, aktiv)
SELECT '4600', 'Erlöse Software-Lizenzen', 'erloes', 1
WHERE NOT EXISTS (SELECT 1 FROM kontenplan WHERE kontonummer = '4600')
  AND EXISTS (SELECT 1 FROM system_einstellungen WHERE schluessel = 'firmenname' AND wert LIKE 'MEALANA%');

INSERT INTO artikel_gruppen (name, konto_nr, aktiv, an_kasse_waehlbar, sortierung)
SELECT 'Lizenzen', '4600', 1, 0, 98
WHERE NOT EXISTS (SELECT 1 FROM artikel_gruppen WHERE name = 'Lizenzen')
  AND EXISTS (SELECT 1 FROM system_einstellungen WHERE schluessel = 'firmenname' AND wert LIKE 'MEALANA%');

-- Welches Lizenz-Modul ein Artikel freischaltet: Modul-Code (Lizenz::MODULE), 'kasse+'/'shop+' =
-- eine weitere Kasse/ein weiterer Shop je Stück, 'wartung' = Wartung um Menge × 1 Jahr verlängern
ALTER TABLE artikel
    ADD COLUMN lizenz_modul VARCHAR(20) NULL AFTER keine_lagerbestandsfuehrung;

CREATE TEMPORARY TABLE tmp_lizenz_artikel (
    artikelnummer VARCHAR(30), name VARCHAR(255), modul VARCHAR(20) NULL, netto DECIMAL(10,2), sortierung INT
);
INSERT INTO tmp_lizenz_artikel VALUES
    ('LZ-GRUND',         'NAHTLOS Grundmodul (Verkauf, Lager, Picklisten, Packplatz)', 'grundmodul',    790.00,  1),
    ('LZ-KASSE',         'NAHTLOS Kasse (1 Kasse, inkl. Offline-/Messe-Kasse)',        'kasse',         590.00,  2),
    ('LZ-KASSE-PLUS',    'NAHTLOS zusätzliche Kasse',                                  'kasse+',        190.00,  3),
    ('LZ-SHOP',          'NAHTLOS Shop-Anbindung (1 Shop)',                            'shop',          490.00,  4),
    ('LZ-SHOP-PLUS',     'NAHTLOS weiterer Shop',                                      'shop+',         190.00,  5),
    ('LZ-GUTSCHEINE',    'NAHTLOS Gutscheine',                                         'gutscheine',    190.00,  6),
    ('LZ-PLC',           'NAHTLOS Versand: PLC-Anbindung',                             'plc',            50.00,  7),
    ('LZ-LAGERPLAETZE',  'NAHTLOS Lagerplätze & Profi-Inventur',                       'lagerplaetze',  390.00,  8),
    ('LZ-PARTNER',       'NAHTLOS Partner / Mietfächer',                               'partner',       390.00,  9),
    ('LZ-HAENDLER',      'NAHTLOS Händler-Konsignation',                               'haendler',      290.00, 10),
    ('LZ-DROPSHIPPING',  'NAHTLOS Dropshipping',                                       'dropshipping',  190.00, 11),
    ('LZ-BUCHHALTUNG',   'NAHTLOS Buchhaltungs-Export',                                'buchhaltung',   190.00, 12),
    ('LZ-KONFIGURATOR',  'NAHTLOS Konfigurator',                                       'konfigurator',  190.00, 13),
    ('LZ-KUNDENANZEIGE', 'NAHTLOS Kundenanzeige',                                      'kundenanzeige',  99.00, 14),
    ('LZ-PAPERLESS',     'NAHTLOS Paperless-Rechnung',                                 'paperless',      99.00, 15),
    ('LZ-WARTUNG',       'NAHTLOS Wartung 1 Jahr (Updates)',                           'wartung',         0.00, 16),
    ('BFR-SIGNATUR',     'BFR Signatureinheit je RKSV-Kasse (inkl. Zertifikat 5 Jahre)', NULL,          189.00, 17),
    ('BFR-SERVICE-JAHR', 'BFR Service 12 Monate (7,99 €/Monat)',                       NULL,             95.88, 18);

INSERT INTO artikel (artikelnummer, name, artikeltyp_id, artikel_gruppe_id, steuerklasse_id, einheit_id,
                     keine_lagerbestandsfuehrung, lizenz_modul, aktiv)
SELECT t.artikelnummer, t.name,
       (SELECT id FROM artikel_typen WHERE code = 'LIZENZ' LIMIT 1),
       (SELECT id FROM artikel_gruppen WHERE name = 'Lizenzen' LIMIT 1),
       (SELECT id FROM steuerklassen WHERE satz = 20 ORDER BY id LIMIT 1),
       (SELECT id FROM einheiten WHERE name LIKE 'St%ck' OR kuerzel = 'Stk' ORDER BY id LIMIT 1),
       1, t.modul, 1
FROM tmp_lizenz_artikel t
WHERE NOT EXISTS (SELECT 1 FROM artikel a WHERE a.artikelnummer = t.artikelnummer)
  AND EXISTS (SELECT 1 FROM system_einstellungen WHERE schluessel = 'firmenname' AND wert LIKE 'MEALANA%');

INSERT INTO artikel_kategorien (artikel_id, kategorie_id)
SELECT a.id, (SELECT id FROM kategorien WHERE name = 'Lizenzen' AND parent_id IS NULL LIMIT 1)
FROM artikel a JOIN tmp_lizenz_artikel t ON t.artikelnummer = a.artikelnummer
WHERE NOT EXISTS (SELECT 1 FROM artikel_kategorien ak WHERE ak.artikel_id = a.id);

INSERT INTO artikel_preise (artikel_id, kundengruppen_id, brutto_vk, netto_vk)
SELECT a.id, (SELECT id FROM kundengruppen ORDER BY ist_standard DESC, id LIMIT 1), ROUND(t.netto * 1.2, 2), t.netto
FROM artikel a JOIN tmp_lizenz_artikel t ON t.artikelnummer = a.artikelnummer
WHERE NOT EXISTS (SELECT 1 FROM artikel_preise p WHERE p.artikel_id = a.id)
  AND EXISTS (SELECT 1 FROM kundengruppen);   -- frische Installation ohne Kundengruppen: Preise später

DROP TEMPORARY TABLE tmp_lizenz_artikel;

-- Lizenzkunden: eine Zeile je Kundeninstallation (feste Lizenznummer)
CREATE TABLE IF NOT EXISTS lizenz_kunden (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lizenz_nr     VARCHAR(20)  NOT NULL,
    produkt       CHAR(2)      NOT NULL DEFAULT '10',
    laufnr        INT UNSIGNED NOT NULL,
    kunde_id      INT UNSIGNED NULL,
    firma         VARCHAR(255) NOT NULL,
    uid           VARCHAR(30)  NULL,
    steuernummer  VARCHAR(50)  NULL,
    notiz         TEXT         NULL,
    erstellt_am   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_lizenz_nr (lizenz_nr),
    UNIQUE KEY uk_lizenz_laufnr (laufnr),
    KEY idx_lizenz_kunde (kunde_id),
    CONSTRAINT fk_lizenz_kunde FOREIGN KEY (kunde_id) REFERENCES kunden (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ausgaben: jede ausgestellte Lizenzdatei (Inhalt bleibt zum erneuten Herunterladen)
CREATE TABLE IF NOT EXISTS lizenz_ausgaben (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lizenz_kunde_id  INT UNSIGNED NOT NULL,
    ausgabe          INT UNSIGNED NOT NULL,
    auftrag_id       INT UNSIGNED NULL,
    module           TEXT         NOT NULL COMMENT 'JSON {code: anzahl}',
    wartung_bis      DATE         NULL COMMENT 'NULL = unbegrenzt (Hauslizenz)',
    hauslizenz       TINYINT(1)   NOT NULL DEFAULT 0,
    grund            VARCHAR(255) NULL,
    inhalt           TEXT         NOT NULL,
    benutzer_id      INT UNSIGNED NULL,
    erstellt_am      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_lizenz_ausgabe (lizenz_kunde_id, ausgabe),
    CONSTRAINT fk_ausgabe_kunde   FOREIGN KEY (lizenz_kunde_id) REFERENCES lizenz_kunden (id),
    CONSTRAINT fk_ausgabe_auftrag FOREIGN KEY (auftrag_id) REFERENCES auftraege (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO system_einstellungen (schluessel, wert)
SELECT 'lizenz_privat_schluessel_pfad', ''
WHERE NOT EXISTS (SELECT 1 FROM system_einstellungen WHERE schluessel = 'lizenz_privat_schluessel_pfad');
