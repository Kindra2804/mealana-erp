-- 238: Versandkosten-Rechnung KG -> Online-Partner (Jacky 2026-11-13, "Versandkosten Rgn. wie Fachmiete")
-- Die Kunden zahlen den Versand auf der Rechnung des Partners (z.B. R-INDRA-…); Versand/Porto gehört aber der KG.
-- Die KG stellt dem Partner monatlich die Versandkosten seiner Rechnungen des Vormonats in Rechnung
-- (eigene Ausgangsrechnung, Warengruppe Versandkosten, 20 %), genau wie die Fachmiete. Keine Gegenverrechnung.
-- Wiederholbar.

CREATE TABLE IF NOT EXISTS partner_versand_rechnungen (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    partner_id    INT UNSIGNED NOT NULL,
    periode       CHAR(7)      NOT NULL,                -- JJJJ-MM (Monat der Partner-Rechnungen)
    betrag_brutto DECIMAL(10,2) NOT NULL,
    auftrag_id    INT UNSIGNED NOT NULL,
    rechnung_id   INT UNSIGNED NOT NULL,
    erstellt_am   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pvr_partner_periode (partner_id, periode),
    CONSTRAINT fk_pvr_partner  FOREIGN KEY (partner_id)  REFERENCES partner (id),
    CONSTRAINT fk_pvr_auftrag  FOREIGN KEY (auftrag_id)  REFERENCES auftraege (id),
    CONSTRAINT fk_pvr_rechnung FOREIGN KEY (rechnung_id) REFERENCES rechnungen (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leistungsartikel wie FACHMIETE (keine Lagerführung, kein Shop), Warengruppe Versandkosten
INSERT INTO artikel (artikelnummer, name, artikeltyp_id, steuerklasse_id, artikel_gruppe_id, einheit_id,
                     keine_lagerbestandsfuehrung, aktiv)
SELECT 'VERSAND-PARTNER', 'Versandkosten (Weiterverrechnung an Partner)', f.artikeltyp_id, f.steuerklasse_id,
       (SELECT id FROM artikel_gruppen WHERE name = 'Versandkosten' ORDER BY id LIMIT 1), f.einheit_id, 1, 1
FROM artikel f
WHERE f.artikelnummer = 'FACHMIETE'
  AND NOT EXISTS (SELECT 1 FROM artikel WHERE artikelnummer = 'VERSAND-PARTNER');
