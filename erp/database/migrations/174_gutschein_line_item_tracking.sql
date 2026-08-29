-- Gutschein-Modul: Idempotenz-Tracking fuer den Shop-Gutschein-Artikel-Kauf.
-- Verhindert doppelte Gutschein-Erzeugung, wenn derselbe Bestellungs-Sync-Poll
-- (z.B. wegen eines spaeteren Statuswechsels) mehrfach ueber dieselbe Bestellung
-- laeuft -- siehe ShopBestellungSyncService::verarbeiteGutscheinKauf().
ALTER TABLE gutscheine
    ADD COLUMN kanal_line_item_id VARCHAR(50) NULL AFTER auftrag_id_ursprung,
    ADD INDEX idx_gutschein_line_item (auftrag_id_ursprung, kanal_line_item_id);
