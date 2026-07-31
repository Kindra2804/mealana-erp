-- Achsenwert-Umbenennungen (z.B. die "Nummer Name"-Sortierumstellung 2026-07-31)
-- wurden im Shop-Sync bisher NIE nachgezogen: ein Wert-Term wurde nur EINMAL
-- angelegt, danach nie wieder auf Textänderungen geprüft -- anders als
-- Kategorien/Hersteller, die schon eine aktualisiert_am/synced_at-Change-
-- Detection haben (siehe ShopSyncRepository::findFaelligeKategorien/-Hersteller).
-- Gleiches Muster hier nachgezogen.
ALTER TABLE varianten_achse_werte
    ADD COLUMN aktualisiert_am TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER sort_order;

-- synced_at bewusst NULL für bereits bestehende Zuordnungen (kein Backfill-UPDATE
-- nötig) -- der Sync-Code behandelt NULL wie "noch nie synct" und zieht die
-- schon vorhandenen Term-Zuordnungen beim nächsten Lauf einmalig nach.
ALTER TABLE varianten_achse_werte_shops
    ADD COLUMN synced_at TIMESTAMP NULL DEFAULT NULL AFTER externe_term_id;
