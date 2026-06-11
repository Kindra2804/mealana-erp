INSERT INTO varianten_achsen (name, code, darstellungsform, sort_order)
VALUES ('Farbe', 'farbe', 'swatches', 0);

INSERT INTO artikel_achsen(
    artikel_id,
    achse_id
)
SELECT DISTINCT
    a.vaterartikel_id,
    h.id
FROM artikel a
INNER JOIN varianten_achsen h ON h.code = 'farbe'
WHERE a.farbe_name IS NOT NULL
  AND a.vaterartikel_id IS NOT NULL;

INSERT INTO varianten_achse_werte(
    artikel_id,
    achse_id,
    wert,
    wert_zusatz,
    sort_order
)
SELECT
    a.vaterartikel_id,
    h.id,
    a.farbe_name,
    a.farbe_hex,
    0
FROM artikel a
INNER JOIN varianten_achsen h ON h.code = 'farbe'
WHERE a.farbe_name IS NOT NULL
AND a.vaterartikel_id IS NOT NULL;

INSERT INTO varianten_kombination_werte (
    kombination_id,
    wert_id
)
SELECT
    a.id,
    vaw.id
FROM artikel a
JOIN varianten_achsen h ON h.code = 'farbe'
JOIN varianten_achse_werte vaw ON vaw.artikel_id = a.vaterartikel_id 
                                AND vaw.achse_id = h.id
                                AND vaw.wert = a.farbe_name
WHERE a.farbe_name IS NOT NULL
AND a.vaterartikel_id IS NOT NULL;
                  

