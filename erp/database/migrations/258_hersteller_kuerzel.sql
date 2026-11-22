-- 258: Herstellerkürzel für das neue Artikelnummern-Schema (Babsi/Jacky 2026-11-21/22)
--
-- Neues Schema: Artikelnummer = Kürzel-Vater-Kind, Kind 4-stellig (z.B. LN-113-0001),
-- Name = "Vater Farbe [Art] (0001)". Das Kürzel (LN, PRY, BC ...) gehört zum Hersteller.
-- Vorbefüllt mit dem häufigsten reinen Buchstaben-Anfang der Vaternummern je Hersteller
-- (Elisa: EL26/EL30/BEL -> EL), danach in der Hersteller-Maske änderbar.
-- "Sonstige" bleibt leer (gemischte Nummern). Nicht eindeutig: Lang Yarns und WOOLADDICTS teilen LY.
--
-- Die alte JTL-Artikelnummer wird NICHT hier gespeichert, sondern in artikel_externe_referenzen
-- (datenquelle = 'jtl'), damit Lagerabgleich, JTL-Archivimport und Kassen-Scan alter Etiketten
-- weiter über die JTL-Nummer laufen. Wiederholbar.

ALTER TABLE hersteller
    ADD COLUMN IF NOT EXISTS kuerzel VARCHAR(10) NULL AFTER handelsname;

UPDATE hersteller h
JOIN (
    SELECT hersteller_id, praefix
    FROM (
        SELECT hersteller_id,
               praefix,
               ROW_NUMBER() OVER (PARTITION BY hersteller_id ORDER BY COUNT(*) DESC, praefix) AS rang
        FROM (
            SELECT hersteller_id, UPPER(REGEXP_SUBSTR(artikelnummer, '^[A-Za-z]+')) AS praefix
            FROM artikel
            WHERE ist_vater = 1 AND hersteller_id IS NOT NULL
        ) roh
        WHERE praefix <> ''
        GROUP BY hersteller_id, praefix
    ) gezaehlt
    WHERE rang = 1
) vorschlag ON vorschlag.hersteller_id = h.id
SET h.kuerzel = LEFT(vorschlag.praefix, 10)
WHERE (h.kuerzel IS NULL OR h.kuerzel = '')
  AND h.name <> 'Sonstige';
