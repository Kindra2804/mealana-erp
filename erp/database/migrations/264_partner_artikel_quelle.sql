-- 264: Mietfach-Artikel aus dem NAHTLOS des Partners übernehmen (Jacky 2026-11-25)
--
-- Ein Mietfach-Inhaber mit eigenem NAHTLOS exportiert seine Artikel (Artikelliste → Massenaktion
-- „Für Mietfach exportieren“), der Mietfach-Betreiber liest die Datei beim Partner ein
-- (Partner → Artikel → „Aus NAHTLOS-Datei übernehmen“). Es entstehen ganz normale, flache
-- Partnerartikel (XP-Nummer, Partner-Lager); Varianten werden dabei zu einzelnen Artikeln.
--
-- Diese Tabelle merkt sich je Partnerartikel, aus welcher Artikelnummer des Partners er stammt.
-- Damit erkennt ein erneuter Import vorhandene Artikel wieder (nichts doppelt) und weiß:
--   partner_preis  = zuletzt übernommener Preis des Partners (brutto)
--   eigener_preis  = 1, wenn bei uns ein eigener Fachpreis gesetzt wurde (weicht vom Partnerpreis ab);
--                    dann bleibt unser Preis beim nächsten Import, der neue Partnerpreis ist nur ein Hinweis.
-- Wiederholbar.

CREATE TABLE IF NOT EXISTS partner_artikel_quelle (
    id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    partner_id         INT UNSIGNED  NOT NULL,
    quell_nummer       VARCHAR(60)   NOT NULL COMMENT 'Artikelnummer im ERP des Partners',
    artikel_id         INT UNSIGNED  NOT NULL COMMENT 'unser Partnerartikel (XP-Nummer)',
    partner_preis      DECIMAL(10,2) NULL     COMMENT 'zuletzt übernommener Bruttopreis des Partners',
    eigener_preis      TINYINT(1)    NOT NULL DEFAULT 0,
    zuletzt_importiert DATETIME      NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_partner_quelle (partner_id, quell_nummer),
    UNIQUE KEY uq_artikel (artikel_id),
    CONSTRAINT fk_paq_artikel FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
