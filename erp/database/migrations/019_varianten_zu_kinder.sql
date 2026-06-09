-- ============================================================
-- Migration 019: artikel_varianten → artikel (Vater-Kind)
-- Alle Varianten werden zu echten Kind-Artikeln in artikel.
-- ============================================================

-- Teil 1: neue Spalten auf artikel
ALTER TABLE artikel
    ADD COLUMN vaterartikel_id    INT UNSIGNED NULL           AFTER ist_vater,
    ADD COLUMN hat_eigenen_lagerstand TINYINT(1) NOT NULL DEFAULT 1 AFTER vaterartikel_id,
    ADD COLUMN farbe_name         VARCHAR(100) NULL           AFTER hat_eigenen_lagerstand,
    ADD COLUMN farbe_hex          VARCHAR(7)   NULL           AFTER farbe_name;

-- Teil 2: Kind-Artikel aus artikel_varianten als echte Artikel anlegen
-- Nicht-kind-spezifische Felder werden vom Vater geerbt
INSERT INTO artikel (
    vaterartikel_id, artikelnummer,
    hersteller_id, steuerklasse_id, artikeltyp_id,
    name, einheit_id, inhalt_menge, inhalt_einheit,
    gewicht_artikel, gewicht_versand, herkunftsland, taric_code,
    varianten_darstellung, grundpreis_bezugsmenge, grundpreis_anzeigen,
    charge_pflicht, ist_auslaufartikel, aktiv,
    farbe_name, farbe_hex, erstellt_am
)
SELECT
    av.artikel_id,
    av.artikelnummer,
    vater.hersteller_id,
    vater.steuerklasse_id,
    vater.artikeltyp_id,
    CONCAT(vater.name, ' – ', av.farbe_name),
    vater.einheit_id,
    vater.inhalt_menge,
    vater.inhalt_einheit,
    vater.gewicht_artikel,
    vater.gewicht_versand,
    vater.herkunftsland,
    vater.taric_code,
    vater.varianten_darstellung,
    vater.grundpreis_bezugsmenge,
    vater.grundpreis_anzeigen,
    vater.charge_pflicht,
    av.ist_auslaufartikel,
    av.aktiv,
    av.farbe_name,
    av.farbe_hex,
    av.erstellt_am
FROM artikel_varianten av
INNER JOIN artikel vater ON vater.id = av.artikel_id;

-- Teil 3: GTINs aus artikel_varianten → artikel_codes (Mapping über artikelnummer)
INSERT INTO artikel_codes (artikel_id, typ, code)
SELECT kind.id, 'GTIN13', av.gtin
FROM artikel_varianten av
INNER JOIN artikel kind
    ON kind.vaterartikel_id = av.artikel_id
    AND kind.artikelnummer = av.artikelnummer
WHERE av.gtin IS NOT NULL AND av.gtin != '';

-- Teil 4: Preise aus artikel_varianten → artikel_preise
INSERT INTO artikel_preise (artikel_id, kundengruppen_id, brutto_vk, netto_vk)
SELECT
    kind.id,
    1,
    av.brutto_vk,
    ROUND(av.brutto_vk / (1 + sk.satz / 100), 4)
FROM artikel_varianten av
INNER JOIN artikel kind
    ON kind.vaterartikel_id = av.artikel_id
    AND kind.artikelnummer = av.artikelnummer
INNER JOIN artikel vater ON vater.id = av.artikel_id
INNER JOIN steuerklassen sk ON sk.id = vater.steuerklasse_id
WHERE av.brutto_vk IS NOT NULL;

-- Teil 5: lagerbestand auf neue Kind-Artikel-IDs umhängen
UPDATE lagerbestand lb
INNER JOIN artikel_varianten av ON lb.artikel_varianten_id = av.id
INNER JOIN artikel kind
    ON kind.vaterartikel_id = av.artikel_id
    AND kind.artikelnummer = av.artikelnummer
SET lb.artikel_id = kind.id, lb.artikel_varianten_id = NULL;

-- Teil 6: lager_bewegungen auf neue Kind-Artikel-IDs umhängen
UPDATE lager_bewegungen lbew
INNER JOIN artikel_varianten av ON lbew.artikel_varianten_id = av.id
INNER JOIN artikel kind
    ON kind.vaterartikel_id = av.artikel_id
    AND kind.artikelnummer = av.artikelnummer
SET lbew.artikel_id = kind.id, lbew.artikel_varianten_id = NULL;

-- Teil 7: FK-Constraints + Spalte artikel_varianten_id aus lagerbestand entfernen
ALTER TABLE lagerbestand
    DROP FOREIGN KEY fk_artikel_varianten,
    DROP INDEX uk_variante_lager_charge,
    DROP COLUMN artikel_varianten_id;

-- Teil 8: FK-Constraint + Spalte artikel_varianten_id aus lager_bewegungen entfernen
ALTER TABLE lager_bewegungen
    DROP FOREIGN KEY fk_lager_bewegungen_artikel_varianten_id,
    DROP KEY fk_lager_bewegungen_artikel_varianten_id,
    DROP COLUMN artikel_varianten_id;

-- Teil 9: artikel_varianten Tabelle entfernen
ALTER TABLE artikel_varianten DROP FOREIGN KEY fk_varianten_artikel;
DROP TABLE artikel_varianten;

-- Teil 10: Self-Referenz FK auf artikel.vaterartikel_id
ALTER TABLE artikel
    ADD CONSTRAINT fk_artikel_vater
    FOREIGN KEY (vaterartikel_id) REFERENCES artikel (id)
    ON DELETE RESTRICT ON UPDATE CASCADE;
