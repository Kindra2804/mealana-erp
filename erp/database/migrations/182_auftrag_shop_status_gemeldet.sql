-- ERP → Shop: welchen WooCommerce-Status hat die Shop-Bestellung zuletzt (bekannt
-- durch Abgleich oder von uns gemeldet)? 'processing' | 'completed' | 'versand_notiz'
-- (versendet, aber unbezahlt -> nur Notiz, Status bleibt). Der Cron meldet nur, wenn
-- sich der Soll-Status aus dem ERP davon unterscheidet (ShopBestellungSyncService::
-- meldeStatusAnShop) -- sonst gäbe es bei jedem Lauf dieselbe Notiz nochmal.
ALTER TABLE auftraege
    ADD COLUMN shop_status_gemeldet VARCHAR(20) NULL AFTER kanal_auftrag_id;

-- Bestehende Shop-Aufträge: aktuellen Soll-Status als "schon gemeldet" vormerken,
-- damit beim ersten Cron-Lauf nach dem Update nicht alle Altbestellungen eine
-- Notiz/Statusänderung bekommen.
UPDATE auftraege
SET shop_status_gemeldet = CASE
        WHEN zahlungsstatus = 'bezahlt' AND lieferstatus IN ('versendet', 'abgeschlossen') THEN 'completed'
        WHEN zahlungsstatus = 'bezahlt' THEN 'processing'
        WHEN lieferstatus IN ('versendet', 'abgeschlossen') THEN 'versand_notiz'
        ELSE NULL
    END
WHERE kanal = 'woocommerce' AND kanal_auftrag_id IS NOT NULL;
