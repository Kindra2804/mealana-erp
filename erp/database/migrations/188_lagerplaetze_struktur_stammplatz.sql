-- Lagerplätze strukturiert (Jacky 2026-10-01): Kürzel aus optionalem Bereich + Regal +
-- Fach, z.B. "R3-F12" im Laden oder "K-R1-F4" im Keller (Nachfüller). bezeichnung
-- bleibt die angezeigte Kurzform (wird aus den Teilen gebildet), sortierung ergibt die
-- Laufweg-Reihenfolge (Bereich, Regal und Fach numerisch).
ALTER TABLE lagerplaetze
    ADD COLUMN bereich    VARCHAR(20) NULL AFTER lager_id,
    ADD COLUMN regal      VARCHAR(10) NULL AFTER bereich,
    ADD COLUMN fach       VARCHAR(10) NULL AFTER regal,
    ADD COLUMN sortierung VARCHAR(60) NOT NULL DEFAULT '' AFTER bezeichnung,
    ADD UNIQUE KEY uq_lagerplatz_bezeichnung (lager_id, bezeichnung);

-- Stammplatz (Verkaufsfach) + Nachfüllplatz (z.B. Keller) je Artikel bzw. Variante.
-- Mehrere Chargen liegen im selben Fach -- der Platz hängt am Artikel, nicht an der Charge.
-- Der Bestand selbst wird weiterhin nur je Lager geführt (Variante A, kein WMS).
ALTER TABLE artikel
    ADD COLUMN stammplatz_id     INT UNSIGNED NULL,
    ADD COLUMN nachfuellplatz_id INT UNSIGNED NULL,
    ADD CONSTRAINT fk_artikel_stammplatz     FOREIGN KEY (stammplatz_id)     REFERENCES lagerplaetze(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_artikel_nachfuellplatz FOREIGN KEY (nachfuellplatz_id) REFERENCES lagerplaetze(id) ON DELETE SET NULL;
