-- Migration 215: Dropshipping (Streckengeschäft)
--
-- Jacky 2026-10-31: Lieferant schickt direkt an den Endkunden — vorerst nur Lang Yarns.
-- Bestellt wird von Hand im Lang-Händlerportal (kein PDF/Mail an den Lieferanten),
-- die Versandmail von Lang kommt an uns, Tracking wird bei uns eingetragen.
-- Strecke nur, wenn der GANZE Auftrag dropship-fähig ist (keine gemischten Aufträge).
--
-- Dropship-fähig ist ein Artikel, wenn seine Lieferanten-Zuordnung es erlaubt:
--   artikel_lieferanten.dropshipping NULL = wie beim Lieferanten (lieferanten.dropshipping_aktiv)
--                                    0    = ausgenommen
--                                    1    = ja (auch wenn der Lieferant sonst nicht)
-- Lang-Rechnung: Artikel zum normalen EK + eine Pauschale pro Lieferung (~11 € netto,
-- Bearbeitung + Versandanteil) → lieferanten.dropship_pauschale als Vorgabe,
-- auf der Dropship-Bestellung in bestellungen.dropship_pauschale änderbar.

ALTER TABLE lieferanten
    ADD COLUMN dropshipping_aktiv  TINYINT(1)    NOT NULL DEFAULT 0 AFTER lieferbedingung,
    ADD COLUMN dropship_pauschale  DECIMAL(10,2) NULL COMMENT 'Netto-Pauschale pro Dropship-Lieferung, Vorgabe für die Bestellung' AFTER dropshipping_aktiv;

ALTER TABLE artikel_lieferanten
    ADD COLUMN dropshipping TINYINT(1) NULL COMMENT 'NULL = wie Lieferant, 0 = ausgenommen, 1 = ja' AFTER standard_lieferant;

-- Dropship-Bestellung = normale Bestellung mit Bezug zum Kundenauftrag.
-- Lieferadresse wird beim Anlegen festgehalten (Kunde könnte sie im Auftrag später ändern).
ALTER TABLE bestellungen
    ADD COLUMN typ ENUM('normal','dropshipping') NOT NULL DEFAULT 'normal' AFTER lieferant_id,
    ADD COLUMN auftrag_id INT UNSIGNED NULL AFTER typ,
    ADD COLUMN lieferadresse_snapshot TEXT NULL AFTER auftrag_id,
    ADD COLUMN dropship_pauschale DECIMAL(10,2) NULL AFTER lieferadresse_snapshot,
    ADD KEY idx_best_auftrag (auftrag_id),
    ADD CONSTRAINT fk_best_auftrag FOREIGN KEY (auftrag_id) REFERENCES auftraege (id) ON UPDATE CASCADE;

-- Lieferung ohne eigenen Versand (Lieferant hat verschickt) → keine Lagerbuchung
ALTER TABLE auftrag_lieferungen
    ADD COLUMN bestellung_id INT UNSIGNED NULL COMMENT 'gesetzt = Dropship-Lieferung durch den Lieferanten' AFTER auftrag_id;

-- Lang Yarns (deutscher Auslieferer) für Dropshipping freischalten, Pauschale lt. Jacky ~11 € netto.
-- Stammdaten vom JTL-Import waren leer/falsch (AT, inland) — Adresse = REO beim Hersteller Lang Yarns,
-- Rechnung kommt aus DE als IGL, 10 Tage netto (Jacky 2026-10-31 / 2026-11-01)
UPDATE lieferanten SET dropshipping_aktiv = 1, dropship_pauschale = 11.00,
       firma = 'LANG GARN & WOLLE GMBH', strasse = 'Puellenweg 20', plz = '41352', ort = 'Korschenbroich',
       land = 'DE', email = COALESCE(email, 'info.de@langyarns.com'), website = COALESCE(website, 'www.langyarns.com'),
       steuerregel = 'eu_igl', zahlungsziel_tage = 10
WHERE name = 'Lang Garn & Wolle GmbH';
