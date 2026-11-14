-- 240: Shop-Sync überträgt ab 1.0.13 die Steuerklasse (tax_class) an WooCommerce (2026-11-14)
-- Vorher lief JEDES Shop-Produkt mit dem Standardsatz 20 % – auch Bücher/Anleitungen (10 %).
-- Fund: Bio-Testbestellung, „Everyday Basics“ mit 20 % statt 10 % verkauft und so ins ERP übernommen.
-- Keine Tabellenänderung: Artikel mit anderem Satz als 20 % (und ihre Väter) als geändert markieren,
-- damit der nächste Sync sie mit der richtigen Steuerklasse neu überträgt.
-- Wiederholbar (setzt nur den Zeitstempel).

UPDATE artikel
   SET aktualisiert_am = NOW()
 WHERE steuerklasse_id IN (SELECT id FROM steuerklassen WHERE satz <> 20.00);

UPDATE artikel v
  JOIN artikel k ON k.vaterartikel_id = v.id
   SET v.aktualisiert_am = NOW()
 WHERE k.steuerklasse_id IN (SELECT id FROM steuerklassen WHERE satz <> 20.00);
