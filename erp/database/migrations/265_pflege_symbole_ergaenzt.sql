-- Migration 265: weitere Pflegesymbole (Jacky/Babsi 2026-11-26, Hersteller-Waschtabelle)
-- Neue Werte im Merkmal "Pflege" (slug pflege, Migration 233). merkmal_werte.symbol = Schlüssel
-- für src/modules/schilder/PflegeSymbole.php (Preisschilder, Anhänger, Kassen-Liste).
-- Einsortiert zwischen die bestehenden Werte (sort_order 10..150 in Zehnerschritten).
-- "Handwäsche" (ohne Temperatur) bleibt unverändert. Schonwaschgang = ein Balken (ISO 3758).
-- Wiederholbar: legt nichts doppelt an (gleicher Schlüssel oder gleicher Text).

INSERT INTO merkmal_werte (merkmal_id, wert, symbol, sort_order)
SELECT m.id, t.wert, t.symbol, t.so
FROM merkmale m
JOIN (
          SELECT 'Handwäsche 30 °C' AS wert,                   'hand30' AS symbol,          12 AS so
    UNION SELECT 'Handwäsche 40 °C',                           'hand40',                    14
    UNION SELECT 'Waschmaschine 40 °C Schonwaschgang',         'wasch40_schon',             35
    UNION SELECT 'Waschmaschine 60 °C',                        'wasch60',                   45
    UNION SELECT 'Trockner erlaubt',                           'trockner',                  75
    UNION SELECT 'Trockner, normale Temperatur',               'trockner_normal',           85
    UNION SELECT 'Bügeln bis 200 °C',                          'buegeln3',                 115
) t
WHERE m.slug = 'pflege'
  AND NOT EXISTS (SELECT 1 FROM merkmal_werte w WHERE w.merkmal_id = m.id AND (w.symbol = t.symbol OR w.wert = t.wert));
