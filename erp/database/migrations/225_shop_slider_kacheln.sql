-- 225: Startseiten-Kacheln im Shop-Slider-Modul (Jacky 2026-11-05)
-- Kacheln (Bild, kleine Zeile, Titel, Ziel) werden wie Slides je Shop im ERP gepflegt und
-- im Shop per [nahtlos_kacheln] angezeigt. Ohne eigenes Bild und mit Kategorie als Ziel
-- nimmt die Übertragung das Kategoriebild aus der WP-Mediathek (kategorie_shops.bild_external_id).
ALTER TABLE shop_slides
    MODIFY COLUMN position ENUM('startseite','kategorie','kachel') NOT NULL DEFAULT 'startseite';
