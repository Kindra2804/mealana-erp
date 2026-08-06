-- Automatische Sichtbarkeit für Aktionskategorien (kategorien.ist_aktions_kategorie):
-- außerhalb des Aktions-Zeitraums (aktionen_kategorien.gueltig_ab/gueltig_bis,
-- aktionen.gestartet) soll die Kategorie im Shop unsichtbar sein (Produkte
-- verlieren die Zuordnung, der Kategorie-Term selbst bleibt in WooCommerce
-- bestehen -- gleiches Prinzip wie beim bestehenden manuellen Kategorie-
-- Ausschluss, Migration 156). Eigene Spalte statt Wiederverwendung von
-- kategorie_shops.synced_at (das trackt den Term selbst, z.B. Name/Beschreibung
-- -- eine Wiederverwendung hätte einen falschen "schon erledigt"-Stand
-- vortäuschen können, wenn der Term aus einem anderen Grund resynct wurde).
ALTER TABLE kategorie_shops
    ADD COLUMN aktion_sichtbarkeit_synced_am TIMESTAMP NULL DEFAULT NULL AFTER ausgeschlossen;
