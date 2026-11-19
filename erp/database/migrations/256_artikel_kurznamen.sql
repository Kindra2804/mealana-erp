-- 256: Kurznamen für Mini-Anhänger 60 x 30 mm (Kassen-Liste, Jacky 2026-11-19)
--
-- Auf die kleinen Anhänger passt der volle Artikelname nicht ("Schlüsselanhänger „Woll-Süchtig“ Kunstleder
-- dunkelbraun" -> "Wollsüchtig - dunkelbr."). Der Kurzname wird im Zwischenschritt vor dem Druck vergeben
-- und hier je Artikel gemerkt, damit er beim nächsten Druck schon da ist.
-- Bewusst eigene Tabelle statt Spalte in artikel: wird nur bei wenigen Artikeln gebraucht.
-- Kein Eintrag = der Name wird automatisch gekürzt (Text in „…“ + Zusatz nach „–“).

CREATE TABLE IF NOT EXISTS artikel_kurznamen (
    artikel_id   INT UNSIGNED NOT NULL,
    kurzname     VARCHAR(60)  NOT NULL,
    geaendert_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (artikel_id),
    CONSTRAINT fk_artkurz_artikel_id FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
