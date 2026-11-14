-- 245: Shop-Preis = Normalpreis statt UVP (Fix 2026-11-14, Paket 1.0.15)
-- Bis 1.0.14 schickte der Shop-Sync die UVP als regular_price. Wich die UVP vom Verkaufspreis ab,
-- verkaufte der Shop zum falschen Preis (Fund: Weihnachtsanhänger VK 4,50, UVP noch 3,50 → Shop 3,50).
-- Außerdem markierte eine Preisänderung den Artikel nicht für den Abgleich (Preise stehen in eigenen
-- Tabellen) – der Komplettabgleich meldete deshalb „keine Änderungen“.
-- Hier: alle Artikel, deren UVP vom Standardpreis abweicht (und ihre Väter), einmal neu übertragen.
-- Keine Tabellenänderung, wiederholbar (setzt nur den Zeitstempel).

UPDATE artikel a
  JOIN artikel_preise p ON p.artikel_id = a.id
  JOIN kundengruppen kg ON kg.id = p.kundengruppen_id AND kg.ist_standard = 1
   SET a.aktualisiert_am = NOW()
 WHERE a.uvp IS NOT NULL AND a.uvp > 0 AND ABS(a.uvp - p.brutto_vk) > 0.004;

UPDATE artikel v
  JOIN artikel k ON k.vaterartikel_id = v.id
  JOIN artikel_preise p ON p.artikel_id = k.id
  JOIN kundengruppen kg ON kg.id = p.kundengruppen_id AND kg.ist_standard = 1
   SET v.aktualisiert_am = NOW()
 WHERE k.uvp IS NOT NULL AND k.uvp > 0 AND ABS(k.uvp - p.brutto_vk) > 0.004;
