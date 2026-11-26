-- Migration 266: Merkmale gehen als WooCommerce-Attribute in den Shop (Jacky 2026-11-26)
-- artikel_shops.merkmale_hash = Fingerabdruck (MD5 der merkmal_wert_ids) der Merkmale, die beim
-- letzten erfolgreichen Abgleich gesendet wurden. Weicht er vom aktuellen Stand ab, ist der
-- Vater/Einzelartikel fällig -- egal über welchen Weg die Merkmale geändert wurden (Detail-Tab,
-- Massenaktion, JTL-/Lang-Import, Vererbung), denn keiner davon ändert artikel.aktualisiert_am.
-- NULL bei allen bestehenden Zeilen: Artikel MIT Merkmalen werden nach dem Update einmal neu
-- übertragen, Artikel ohne Merkmale nicht.

ALTER TABLE artikel_shops ADD COLUMN IF NOT EXISTS merkmale_hash CHAR(32) NULL AFTER synced_at;
