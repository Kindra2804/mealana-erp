-- 255: Varianten (Kinder), die der Generator angelegt hat, bekamen bis 2026-11-16 nur 10 Felder vom Vater
-- (VariantenRepository::insertKindArtikel). Inhalt, Gewicht, Grundpreis, Texte, Maße, TARIC usw. blieben leer,
-- bis jemand den Vater speicherte (Fund Jacky beim Lang-Import, LY-1168-0002: Grundpreis nicht angehakt).
-- Füllt NUR LEERE Felder vom Vater auf -- eigene Werte der Kinder (z.B. abweichende Maße) bleiben.
-- Wiederholbar.

-- Merker: Grundpreis-Bezugsmenge leer beim Kind, beim Vater gesetzt = beim Anlegen nichts geerbt.
-- Die nicht-NULL-Felder (Grundpreis anzeigen, Überverkauf) nur in diesem Fall, und VOR der Bezugsmenge
-- setzen (MariaDB rechnet die SET-Liste von links nach rechts mit den neuen Werten).
UPDATE artikel k
JOIN artikel v ON v.id = k.vaterartikel_id
SET k.grundpreis_anzeigen  = IF(k.grundpreis_bezugsmenge IS NULL AND v.grundpreis_bezugsmenge IS NOT NULL, v.grundpreis_anzeigen, k.grundpreis_anzeigen),
    k.ueberverkauf_erlaubt = IF(k.grundpreis_bezugsmenge IS NULL AND v.grundpreis_bezugsmenge IS NOT NULL, v.ueberverkauf_erlaubt, k.ueberverkauf_erlaubt),
    k.grundpreis_bezugsmenge = COALESCE(k.grundpreis_bezugsmenge, v.grundpreis_bezugsmenge),
    k.inhalt_menge       = COALESCE(k.inhalt_menge, v.inhalt_menge),
    k.inhalt_einheit     = COALESCE(k.inhalt_einheit, v.inhalt_einheit),
    k.gewicht_artikel    = COALESCE(k.gewicht_artikel, v.gewicht_artikel),
    k.gewicht_versand    = COALESCE(k.gewicht_versand, v.gewicht_versand),
    k.laenge             = COALESCE(k.laenge, v.laenge),
    k.breite             = COALESCE(k.breite, v.breite),
    k.hoehe              = COALESCE(k.hoehe, v.hoehe),
    k.herkunftsland      = COALESCE(k.herkunftsland, v.herkunftsland),
    k.taric_code         = COALESCE(k.taric_code, v.taric_code),
    k.kurzbeschreibung   = COALESCE(k.kurzbeschreibung, v.kurzbeschreibung),
    k.beschreibung       = COALESCE(k.beschreibung, v.beschreibung),
    k.technische_details = COALESCE(k.technische_details, v.technische_details),
    k.beschreibung_intern = COALESCE(k.beschreibung_intern, v.beschreibung_intern),
    k.meta_titel         = COALESCE(k.meta_titel, v.meta_titel),
    k.meta_description   = COALESCE(k.meta_description, v.meta_description),
    k.aktualisiert_am    = NOW()     -- Shop überträgt Grundpreis/Gewicht neu
WHERE (k.grundpreis_bezugsmenge IS NULL AND v.grundpreis_bezugsmenge IS NOT NULL)
   OR (k.inhalt_menge IS NULL AND v.inhalt_menge IS NOT NULL)
   OR (k.gewicht_artikel IS NULL AND v.gewicht_artikel IS NOT NULL)
   OR (k.gewicht_versand IS NULL AND v.gewicht_versand IS NOT NULL)
   OR (k.laenge IS NULL AND v.laenge IS NOT NULL)
   OR (k.breite IS NULL AND v.breite IS NOT NULL)
   OR (k.hoehe IS NULL AND v.hoehe IS NOT NULL)
   OR (k.herkunftsland IS NULL AND v.herkunftsland IS NOT NULL)
   OR (k.taric_code IS NULL AND v.taric_code IS NOT NULL)
   OR (k.beschreibung IS NULL AND v.beschreibung IS NOT NULL)
   OR (k.kurzbeschreibung IS NULL AND v.kurzbeschreibung IS NOT NULL);
