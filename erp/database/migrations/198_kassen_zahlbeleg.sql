-- Kassen-Zahlbeleg (Jacky 2026-10-07): offene Rechnung an der Kasse bezahlen. Normaler
-- Verkaufs-Bon (RKSV-signiert, zählt in Kassenbuch/X-/Z-Bon) mit einer 0-%-Zeile
-- "Zahlung zu Auftrag ..." (block 'zahlung', web_auftrag_id = bezahlter Auftrag) --
-- gleiches Muster wie der Gutschein-Verkauf. Kein Erlös: die USt steht schon auf der Rechnung.
ALTER TABLE kassen_bon_positionen
    MODIFY COLUMN block ENUM('auftrag','addon','storno','retour','gutschein_kauf','gutschein_verkauf','zahlung') NULL;

-- Eigener Bon-Typ aus Migration 195 wird dafür nicht gebraucht (X-/Z-Bon, Kassenbuch und
-- BFR zählen nur 'verkauf') -- wieder entfernen, solange er nirgends verwendet wird.
ALTER TABLE kassen_bons
    MODIFY COLUMN typ ENUM('verkauf','storno','x_bon','z_bon') NOT NULL DEFAULT 'verkauf';
