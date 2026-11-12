-- 233: Merkmale für Garne neu aufgebaut (Jacky 2026-11-12, Grundlage für Preisschilder + JTL-Merkmal-Import)
--
-- Zwei Arten von Merkmalen:
--   * Genaue Werte fürs Schild (feste Werteliste, Freitext nur über "Wert anlegen"):
--     Zusammensetzung, Gewicht/Länge ("50 g = 125 m"), Maschenprobe
--   * Filter-Merkmale für den Shop: Wollqualität, Garngruppe, Nadelstärke (mehrfach),
--     Lauflänge je 100 g + Maschenprobe/10 cm (werden aus Gewicht/Länge bzw. Maschenprobe automatisch gesetzt), Auszeichnung (BIO/GOTS/Handgefärbt -> Badges)
--   * Pflege (mehrfach) mit Symbol-Schlüssel für die Pflegesymbole am Schild
--
-- merkmal_werte.symbol: technischer Schlüssel (Pflegesymbol bzw. Badge), NULL = keiner.
-- Merkmale werden über den slug erkannt (idempotent, auch auf Live ohne Merkmale lauffähig).

ALTER TABLE merkmal_werte ADD COLUMN IF NOT EXISTS symbol VARCHAR(30) NULL AFTER wert;

-- Dev-Altbestand: "Zusammensetzung" ohne slug enthielt in Wahrheit die Wollqualitäten,
-- Garngruppe hatte keinen slug.
UPDATE merkmale SET name = 'Wollqualität', slug = 'wollqualitaet' WHERE slug = '' AND name = 'Zusammensetzung';
UPDATE merkmale SET slug = 'garngruppe' WHERE slug = '' AND name = 'Garngruppe';
-- Alte Lauflängen-Bereiche "je 50 g" ersetzt durch Lauflänge je 100 g
UPDATE merkmale SET aktiv = 0 WHERE slug = 'lauflaenge_50g';
-- Maschenprobe/10 cm (Filter, klassische Abstufung) bleibt neben der Drops-Garngruppe (Babsi 2026-11-12);
-- Dev-Altwerte (überlappende Bereiche) werden durch feste Bereiche ersetzt
DELETE w FROM merkmal_werte w JOIN merkmale m ON m.id = w.merkmal_id
 WHERE m.slug = 'maschenprobe_10cm' AND w.symbol IS NULL;

-- Merkmale anlegen, falls es sie noch nicht gibt (name, slug, mehrfach, filterbar, sort)
INSERT INTO merkmale (name, slug, einheit, datentyp, filterbar, mehrfach_auswahl, sort_order, aktiv)
SELECT t.name, t.slug, '', 'text', t.filterbar, t.mehrfach, t.sort_order, 1
FROM (
          SELECT 'Wollqualität' AS name, 'wollqualitaet' AS slug, 1 AS filterbar, 1 AS mehrfach, 10 AS sort_order
    UNION SELECT 'Zusammensetzung',       'zusammensetzung',    0, 0, 20
    UNION SELECT 'Gewicht/Länge',         'gewicht_laenge',     0, 0, 30
    UNION SELECT 'Lauflänge je 100 g',    'lauflaenge_bereich', 1, 0, 35
    UNION SELECT 'Nadelstärke',           'nadelstaerke',       1, 1, 40
    UNION SELECT 'Maschenprobe',          'maschenprobe',       0, 0, 50
    UNION SELECT 'Maschenprobe/10 cm',    'maschenprobe_10cm',  1, 0, 55
    UNION SELECT 'Garngruppe',            'garngruppe',         1, 0, 60
    UNION SELECT 'Auszeichnung',          'auszeichnung',       1, 1, 70
    UNION SELECT 'Pflege',                'pflege',             0, 1, 80
) t
WHERE NOT EXISTS (SELECT 1 FROM merkmale m WHERE m.slug = t.slug);

-- Einheitliche Namen/Einstellungen (auch für bereits vorhandene)
UPDATE merkmale SET name = 'Wollqualität',  filterbar = 1, mehrfach_auswahl = 1, sort_order = 10, aktiv = 1 WHERE slug = 'wollqualitaet';
UPDATE merkmale SET name = 'Nadelstärke',   filterbar = 1, mehrfach_auswahl = 1, sort_order = 40, aktiv = 1 WHERE slug = 'nadelstaerke';
UPDATE merkmale SET name = 'Maschenprobe/10 cm', filterbar = 1, mehrfach_auswahl = 0, sort_order = 55, aktiv = 1 WHERE slug = 'maschenprobe_10cm';
UPDATE merkmale SET name = 'Garngruppe',    filterbar = 1, mehrfach_auswahl = 0, sort_order = 60, aktiv = 1 WHERE slug = 'garngruppe';

-- Gruppen (falls vorhanden): Garninfo / Verarbeitung / Pflege
UPDATE merkmale m JOIN merkmal_gruppen g ON g.name = 'Garninfo'
   SET m.merkmal_gruppen_id = g.id WHERE m.slug IN ('wollqualitaet', 'zusammensetzung', 'gewicht_laenge', 'lauflaenge_bereich', 'auszeichnung');
UPDATE merkmale m JOIN merkmal_gruppen g ON g.name = 'Verarbeitung'
   SET m.merkmal_gruppen_id = g.id WHERE m.slug IN ('nadelstaerke', 'maschenprobe', 'maschenprobe_10cm', 'garngruppe');
UPDATE merkmale m JOIN merkmal_gruppen g ON g.name = 'Pflege'
   SET m.merkmal_gruppen_id = g.id WHERE m.slug = 'pflege';

-- Nur für Garne sichtbar (Auszeichnung gilt für alle Artikeltypen)
INSERT IGNORE INTO merkmal_artikeltypen (merkmal_id, artikeltyp_id)
SELECT m.id, t.id FROM merkmale m JOIN artikel_typen t ON t.code = 'GARN'
WHERE m.slug IN ('wollqualitaet', 'zusammensetzung', 'gewicht_laenge', 'lauflaenge_bereich', 'nadelstaerke', 'maschenprobe', 'maschenprobe_10cm', 'garngruppe', 'pflege')
  AND NOT EXISTS (SELECT 1 FROM merkmal_artikeltypen x WHERE x.merkmal_id = m.id AND x.artikeltyp_id = t.id);

-- Feste Werte: Lauflänge je 100 g (Bereiche), Garngruppe A-F, Auszeichnung, Pflege
INSERT INTO merkmal_werte (merkmal_id, wert, symbol, sort_order)
SELECT m.id, t.wert, t.symbol, t.so
FROM merkmale m JOIN (
          SELECT 'lauflaenge_bereich' AS slug, 'bis 150 m' AS wert, 'lb_150' AS symbol, 10 AS so
    UNION SELECT 'lauflaenge_bereich', '151–250 m',  'lb_250', 20
    UNION SELECT 'lauflaenge_bereich', '251–350 m',  'lb_350', 30
    UNION SELECT 'lauflaenge_bereich', '351–450 m',  'lb_450', 40
    UNION SELECT 'lauflaenge_bereich', 'über 450 m', 'lb_mehr', 50
    UNION SELECT 'maschenprobe_10cm', 'bis 10 M',   'mp_10', 10
    UNION SELECT 'maschenprobe_10cm', '11–16 M',    'mp_16', 20
    UNION SELECT 'maschenprobe_10cm', '17–20 M',    'mp_20', 30
    UNION SELECT 'maschenprobe_10cm', '21–25 M',    'mp_25', 40
    UNION SELECT 'maschenprobe_10cm', '26–30 M',    'mp_30', 50
    UNION SELECT 'maschenprobe_10cm', 'über 30 M',  'mp_mehr', 60
    UNION SELECT 'garngruppe', 'A 26 - 23 Maschen', 'gg_a', 10
    UNION SELECT 'garngruppe', 'B 22 - 20 Maschen', 'gg_b', 20
    UNION SELECT 'garngruppe', 'C 19 - 16 Maschen', 'gg_c', 30
    UNION SELECT 'garngruppe', 'D 15 - 12 Maschen', 'gg_d', 40
    UNION SELECT 'garngruppe', 'E 11 - 9 Maschen',  'gg_e', 50
    UNION SELECT 'garngruppe', 'F 8 - 5 Maschen',   'gg_f', 60
    UNION SELECT 'garngruppe', 'Andere Garne',      'gg_andere', 70
    UNION SELECT 'auszeichnung', 'BIO',         'bio', 10
    UNION SELECT 'auszeichnung', 'GOTS',        'gots', 20
    UNION SELECT 'auszeichnung', 'Handgefärbt', 'handgefaerbt', 30
    UNION SELECT 'pflege', 'Handwäsche',                          'handwaesche', 10
    UNION SELECT 'pflege', 'Waschmaschine 30 °C Schonwaschgang',  'wasch30_schon', 20
    UNION SELECT 'pflege', 'Waschmaschine 30 °C',                 'wasch30', 30
    UNION SELECT 'pflege', 'Waschmaschine 40 °C',                 'wasch40', 40
    UNION SELECT 'pflege', 'Nicht waschen',                       'nicht_waschen', 50
    UNION SELECT 'pflege', 'Nicht bleichen',                      'nicht_bleichen', 60
    UNION SELECT 'pflege', 'Nicht im Trockner trocknen',          'kein_trockner', 70
    UNION SELECT 'pflege', 'Trockner, niedrige Temperatur',       'trockner_niedrig', 80
    UNION SELECT 'pflege', 'Liegend trocknen',                    'liegend', 90
    UNION SELECT 'pflege', 'Bügeln bis 110 °C',                   'buegeln1', 100
    UNION SELECT 'pflege', 'Bügeln bis 150 °C',                   'buegeln2', 110
    UNION SELECT 'pflege', 'Nicht bügeln',                        'nicht_buegeln', 120
    UNION SELECT 'pflege', 'Chemische Reinigung (P)',             'reinigung_p', 130
    UNION SELECT 'pflege', 'Schonende chemische Reinigung (P)',   'reinigung_p_schon', 140
    UNION SELECT 'pflege', 'Keine chemische Reinigung',           'nicht_reinigen', 150
) t ON t.slug = m.slug
WHERE NOT EXISTS (SELECT 1 FROM merkmal_werte w WHERE w.merkmal_id = m.id AND (w.symbol = t.symbol OR w.wert = t.wert));

-- Dev: vorhandene Garngruppen-Werte (gleicher Text) bekommen ihren Schlüssel
UPDATE merkmal_werte w JOIN merkmale m ON m.id = w.merkmal_id AND m.slug = 'garngruppe'
   SET w.symbol = CASE LEFT(w.wert, 1) WHEN 'A' THEN 'gg_a' WHEN 'B' THEN 'gg_b' WHEN 'C' THEN 'gg_c'
                                        WHEN 'D' THEN 'gg_d' WHEN 'E' THEN 'gg_e' WHEN 'F' THEN 'gg_f' ELSE 'gg_andere' END
 WHERE w.symbol IS NULL;
