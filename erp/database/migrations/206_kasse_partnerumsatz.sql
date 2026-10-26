-- Migration 206: Partnerware an der Kasse getrennt führen (Partner-Abrechnung Schritt 2)
--
-- Klärung Steuerberatung 2026-10-26:
--   fremdrechnung → Verkauf im Namen und auf Rechnung des Partners: nicht unser Umsatz,
--                   nicht in der RKSV-Signatur, eigener Abschnitt am Bon
--   spende        → Durchlauf, eigener Abschnitt am Bon, Bargeld kommt in die Spendenlade
--   kommission    → ganz normaler eigener Umsatz (nur zur Nachverfolgung markiert)
-- Ein Bon bleibt EIN Beleg für Kunde + Zahlung; bruttobetrag = alles, was der Kunde zahlt.

-- Snapshot je Position (Verkaufsart kann sich später ändern, der Bon nicht)
ALTER TABLE kassen_bon_positionen
    ADD COLUMN partner_id    INT UNSIGNED NULL AFTER artikel_gruppe_id,
    ADD COLUMN partner_modus ENUM('kommission','fremdrechnung','spende') NULL AFTER partner_id,
    ADD INDEX idx_kbp_partner (partner_id, partner_modus);

-- Summen je Bon: eigener Umsatz = bruttobetrag − fremd_betrag − spende_betrag.
-- spende_bar = Bar-Anteil der Spende, liegt in der Spendenlade → nicht im Kassenstand.
ALTER TABLE kassen_bons
    ADD COLUMN fremd_betrag  DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER bruttobetrag,
    ADD COLUMN spende_betrag DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER fremd_betrag,
    ADD COLUMN spende_bar    DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER spende_betrag;

-- Bestehende Bon-Positionen mit Partnerware nachziehen (Verkaufsart von heute).
-- Summen der Alt-Bons bleiben bewusst unverändert: die wurden damals komplett
-- signiert und als eigener Umsatz gezählt.
UPDATE kassen_bon_positionen bp
JOIN artikel a ON a.id = bp.artikel_id
SET bp.partner_id = a.partner_id, bp.partner_modus = 'kommission'
WHERE a.partner_id IS NOT NULL;
