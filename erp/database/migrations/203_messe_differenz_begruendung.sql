-- Migration 203: Papier-Messe — optionale Begründung der Differenz
--
-- Der Messe-Abschluss vergleicht Belegsumme mit Strichliste × Standard-VK.
-- Die Differenz wird nicht gebucht (Umsatz = Belege), ist nur Info. Hier kann
-- man festhalten, woher sie kommt (z.B. "Messe-Rabatt 10 %"). Freiwillig.

ALTER TABLE kassen_messe_sync
    ADD COLUMN differenz_begruendung VARCHAR(500) NULL AFTER notiz;
