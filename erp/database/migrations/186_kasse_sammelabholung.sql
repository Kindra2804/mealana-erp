-- Sammelabholung: mehrere Online-Aufträge EINES Kunden auf einem Kassenbon abholen.
-- kassen_bons.web_auftrag_id bleibt (erster/einziger Auftrag, Altbestand + bestehende
-- Abfragen); die vollständige Liste steht in kassen_bon_auftraege.
CREATE TABLE IF NOT EXISTS kassen_bon_auftraege (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bon_id            INT UNSIGNED NOT NULL,
    auftrag_id        INT UNSIGNED NOT NULL,
    -- war der Auftrag schon vor dem Bon bezahlt? (dann stehen seine Positionen nicht
    -- auf dem Bon, der Druck nennt ihn nur als "bereits bezahlt, abgeholt")
    vorher_bezahlt    TINYINT(1)   NOT NULL DEFAULT 0,
    UNIQUE KEY uq_bon_auftrag (bon_id, auftrag_id),
    KEY idx_auftrag (auftrag_id),
    CONSTRAINT fk_kba_bon     FOREIGN KEY (bon_id)     REFERENCES kassen_bons(id) ON DELETE CASCADE,
    CONSTRAINT fk_kba_auftrag FOREIGN KEY (auftrag_id) REFERENCES auftraege(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bon-Zeile -> aus welchem Auftrag (für Zwischenüberschriften auf dem Druck)
ALTER TABLE kassen_bon_positionen
    ADD COLUMN web_auftrag_id INT UNSIGNED NULL AFTER block;

-- Bestehende Bons mit Web-Auftrag übernehmen (vorher bezahlt = keine Auftrags-Zeilen auf dem Bon)
INSERT IGNORE INTO kassen_bon_auftraege (bon_id, auftrag_id, vorher_bezahlt)
SELECT b.id, b.web_auftrag_id,
       NOT EXISTS (SELECT 1 FROM kassen_bon_positionen p WHERE p.bon_id = b.id AND p.block = 'auftrag')
FROM kassen_bons b
JOIN auftraege a ON a.id = b.web_auftrag_id
WHERE b.web_auftrag_id IS NOT NULL;

UPDATE kassen_bon_positionen p
JOIN kassen_bons b ON b.id = p.bon_id
SET p.web_auftrag_id = b.web_auftrag_id
WHERE p.block IN ('auftrag', 'retour') AND b.web_auftrag_id IS NOT NULL;
