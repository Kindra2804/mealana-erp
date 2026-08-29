-- Bestellungs-Sync: Konfigurator-Auswahl darf nie verloren gehen, auch wenn
-- die Preis-Matrix im Shop veraltet ist (varianten_achse_werte-IDs wurden seit
-- dem letzten Produkt-Sync neu vergeben -- siehe project_konfigurator_modul,
-- Fund vom 2026-08-29: 5 von 7 IDs eines Testauftrags zeigten ins Leere).
-- position_konfiguration.wert_id ist NOT NULL und von mehreren Stellen
-- (Preis-Nachrechnung, Kasse) auf einen echten Wert angewiesen -- statt dort
-- ein Nullable-Wert_id-Sonderfall einzubauen, bekommt auftrag_positionen ein
-- eigenes, robustes Klartext-Feld als Fallback/Ergänzung.

ALTER TABLE auftrag_positionen
    ADD COLUMN konfig_freitext TEXT NULL AFTER gesamtpreis_netto;
