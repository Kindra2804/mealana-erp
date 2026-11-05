-- 227: Widerruf aus dem Shop am Auftrag (Jacky 2026-11-05)
-- Germanized (WooCommerce) hat seit 4.0 den gesetzlichen Widerrufsbutton (EU-RL 2023/2673).
-- Ein Widerruf setzt die Shop-Bestellung auf „Widerruf ausstehend“ (pending-wdraw),
-- bestätigt auf „Widerrufen“ (withdrawn). Der Shop-Abgleich übernimmt das hierher:
--   beantragt  = Kunde hat widerrufen, Händler hat noch nicht entschieden
--   widerrufen = im Shop bestätigt
-- Solange ein Widerruf am Auftrag hängt, sperren Packplatz und Abholung an der Kasse den
-- Auftrag, und das ERP meldet keinen Bestellstatus mehr an den Shop (sonst würde es den
-- Widerrufs-Status in WooCommerce überschreiben). Lehnt der Händler den Widerruf im Shop
-- ab (Status geht zurück), wird die Markierung wieder entfernt.

ALTER TABLE auftraege
    ADD COLUMN widerruf_status ENUM('beantragt','widerrufen') NULL DEFAULT NULL AFTER shop_status_gemeldet,
    ADD COLUMN widerruf_am DATETIME NULL DEFAULT NULL AFTER widerruf_status;
